<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Models\TenantApplication;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * LeaseService — lease records, activation and expiry.
 *
 * Activation is the concurrency-critical path: the unit row is locked
 * (SELECT ... FOR UPDATE) inside a transaction so two simultaneous
 * activations for the same unit cannot both succeed.
 */
class LeaseService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Lease::with(['tenant:id,first_name,last_name', 'unit:id,unit_number', 'property:id,name'])
            ->orderByDesc('id');

        $this->applyScope($query);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', (int) $filters['unit_id']);
        }
        if (! empty($filters['property_id'])) {
            $query->where('property_id', (int) $filters['property_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['expiring_within_days'])) {
            $days = (int) $filters['expiring_within_days'];
            $query->where('status', 'active')
                ->whereDate('end_date', '<=', now()->addDays($days)->toDateString());
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Lease
    {
        $query = Lease::with(['tenant', 'unit', 'property', 'building', 'application', 'previousLease', 'successorLease', 'inspection', 'documents', 'createdBy:id,name']);
        $this->applyScope($query);

        return $query->findOrFail($id);
    }

    /** Create a DRAFT lease. Activation is a separate, guarded step. */
    public function create(array $data): Lease
    {
        $actor = $this->actor();
        $agencyId = $actor->isSuperAdmin() ? ($data['agency_id'] ?? null) : $actor->agency_id;
        if (! $agencyId) {
            abort(422, 'A lease must belong to an agency.');
        }
        $this->ensureAgencyAccess($agencyId);

        $tenant = $this->resolveTenant((int) $data['tenant_id'], $agencyId);
        $unit = $this->resolveUnit((int) $data['unit_id'], $agencyId);

        $this->validateDates($data['start_date'], $data['end_date']);

        if (! empty($data['application_id'])) {
            $this->resolveApplication((int) $data['application_id'], $tenant, $agencyId);
        }

        $lease = DB::transaction(function () use ($data, $agencyId, $tenant, $unit, $actor) {
            $lease = Lease::create([
                ...$data,
                'agency_id' => $agencyId,
                'tenant_id' => $tenant->id,
                'unit_id' => $unit->id,
                'property_id' => $unit->property_id,
                'building_id' => $unit->building_id,
                'status' => 'draft',
                'created_by' => $actor?->id,
                // Temporary; replaced with the formatted number below.
                'lease_number' => 'pending',
            ]);

            $lease->update([
                'lease_number' => sprintf('LSE-%d-%s-%06d', $agencyId, now()->format('Y'), $lease->id),
            ]);

            return $lease;
        });

        $this->audit()->logModelChange('leases.create', $lease);

        return $lease->fresh();
    }

    /** Update a DRAFT lease. Active/history leases are immutable. */
    public function updateDraft(Lease $lease, array $data): Lease
    {
        $this->ensureLeaseAccess($lease);

        if ($lease->status !== 'draft') {
            abort(422, 'Only draft leases can be edited.');
        }

        unset($data['tenant_id'], $data['unit_id'], $data['agency_id']);

        if (isset($data['start_date']) || isset($data['end_date'])) {
            $this->validateDates(
                $data['start_date'] ?? $lease->start_date->toDateString(),
                $data['end_date'] ?? $lease->end_date->toDateString()
            );
        }

        $old = $lease->toArray();
        $lease->update($data);

        $this->audit()->log('leases.update', $lease, $old);

        return $lease->fresh();
    }

    /**
     * Activate a draft lease.
     * - Locks the unit row: concurrent activations serialize here.
     * - Rejects overlapping active leases for the unit.
     * - Unit must be vacant or reserved (never maintenance/inactive/archived).
     * - Marks a previous lease as renewed when this is a successor.
     */
    public function activate(Lease $lease): Lease
    {
        $this->ensureLeaseAccess($lease);

        if ($lease->status !== 'draft') {
            abort(422, 'Only draft leases can be activated.');
        }

        return DB::transaction(function () use ($lease) {
            // Lock the unit: the concurrency guard.
            $unit = Unit::withoutAgencyScope()
                ->where('id', $lease->unit_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($unit->trashed()) {
                abort(422, 'Cannot activate a lease for an archived unit.');
            }

            // Renewal takeover: the unit is occupied by this lease's own
            // predecessor, which flips to "renewed" below. Not a conflict.
            $otherActiveIds = Lease::withoutAgencyScope()
                ->where('unit_id', $unit->id)
                ->where('status', 'active')
                ->where('id', '!=', $lease->id)
                ->pluck('id')->all();
            $isRenewalTakeover = count($otherActiveIds) === 1
                && $lease->previous_lease_id
                && in_array($lease->previous_lease_id, $otherActiveIds, true);

            if (! $isRenewalTakeover) {
                if (in_array($unit->status, ['maintenance', 'inactive'], true)) {
                    abort(422, "Cannot activate a lease while the unit is {$unit->status}.");
                }
                if (! in_array($unit->status, ['vacant', 'reserved'], true)) {
                    abort(422, "Cannot activate a lease while the unit is {$unit->status}.");
                }
            }

            if ($this->hasOverlappingActiveLease($unit->id, $lease->start_date, $lease->end_date, $lease->id)) {
                abort(422, 'This unit already has an active lease overlapping these dates.');
            }

            $lease->update(['status' => 'active', 'activated_at' => now()]);

            // Renewal chain: the predecessor is now history, never overwritten.
            if ($lease->previous_lease_id) {
                Lease::withoutAgencyScope()
                    ->where('id', $lease->previous_lease_id)
                    ->where('status', 'active')
                    ->update(['status' => 'renewed']);
            }

            $unit->update(['status' => 'occupied']);
            $lease->tenant->update(['status' => 'active']);

            $this->audit()->log('leases.activate', $lease);

            return $lease->fresh();
        });
    }

    /** Transition past-due active leases to expired and restore vacancy. */
    public function markExpired(): int
    {
        $count = 0;

        $leases = Lease::where('status', 'active')
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($leases as $lease) {
            DB::transaction(function () use ($lease, &$count) {
                $locked = Lease::where('id', $lease->id)->lockForUpdate()->first();
                if ($locked->status !== 'active') {
                    return; // another process got there first
                }
                $locked->update(['status' => 'expired']);
                $this->refreshUnitOccupancy($locked->unit_id);
                $this->refreshTenantStatus($locked->tenant_id);
                $count++;
            });
            $this->audit()->log('leases.expire', $lease);
        }

        return $count;
    }

    /**
     * Recompute a unit's occupancy from its leases.
     * Rule: any active lease => occupied; otherwise occupied => vacant.
     * Manual states (reserved, maintenance, inactive) are never overwritten.
     */
    public function refreshUnitOccupancy(int $unitId): void
    {
        $unit = Unit::withoutAgencyScope()->find($unitId);
        if (! $unit || $unit->trashed()) {
            return;
        }

        $hasActive = Lease::withoutAgencyScope()
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->exists();

        if ($hasActive && in_array($unit->status, ['vacant', 'reserved'], true)) {
            $unit->update(['status' => 'occupied']);
        } elseif (! $hasActive && $unit->status === 'occupied') {
            $unit->update(['status' => 'vacant']);
        }
    }

    public function refreshTenantStatus(int $tenantId): void
    {
        $tenant = Tenant::withoutAgencyScope()->find($tenantId);
        if (! $tenant || $tenant->trashed()) {
            return;
        }

        $hasActive = Lease::withoutAgencyScope()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();

        $tenant->update(['status' => $hasActive ? 'active' : 'inactive']);
    }

    public function ensureLeaseAccess(Lease $lease): void
    {
        $this->ensureAgencyAccess($lease->agency_id);
        app(TenantService::class)->ensureTenantAccess($lease->tenant);
    }

    private function applyScope($query): void
    {
        $ids = TenantAccess::accessibleTenantIds($this->actor());
        if (! is_null($ids)) {
            $query->whereIn('tenant_id', $ids);
        }
    }

    private function hasOverlappingActiveLease(int $unitId, $start, $end, int $ignoreId): bool
    {
        return Lease::withoutAgencyScope()
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->where('id', '!=', $ignoreId)
            ->where(function ($q) use ($start, $end) {
                $q->where('start_date', '<=', $end)
                  ->where('end_date', '>=', $start);
            })
            ->exists();
    }

    private function validateDates($start, $end): void
    {
        if (strtotime($end) <= strtotime($start)) {
            abort(422, 'The lease end date must be after the start date.');
        }
    }

    private function resolveTenant(int $tenantId, int $agencyId): Tenant
    {
        $tenant = Tenant::withoutAgencyScope()
            ->where('id', $tenantId)
            ->where('agency_id', $agencyId)
            ->firstOrFail();
        app(TenantService::class)->ensureTenantAccess($tenant);

        return $tenant;
    }

    private function resolveUnit(int $unitId, int $agencyId): Unit
    {
        $unit = Unit::withoutAgencyScope()
            ->where('id', $unitId)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $unit) {
            abort(404, 'Unit not found.');
        }
        if ($unit->trashed()) {
            abort(422, 'Cannot create a lease for an archived unit.');
        }

        return $unit;
    }

    private function resolveApplication(int $applicationId, Tenant $tenant, int $agencyId): TenantApplication
    {
        $application = TenantApplication::withoutAgencyScope()
            ->where('id', $applicationId)
            ->where('agency_id', $agencyId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $application) {
            abort(422, 'The selected application does not belong to this tenant and agency.');
        }
        if ($application->status !== 'approved') {
            abort(422, 'A lease can only be created from an approved application.');
        }

        return $application;
    }
}
