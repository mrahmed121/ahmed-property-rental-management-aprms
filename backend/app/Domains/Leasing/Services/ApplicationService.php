<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Models\TenantApplication;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

class ApplicationService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = TenantApplication::with(['tenant:id,first_name,last_name', 'property:id,name', 'unit:id,unit_number'])
            ->orderByDesc('id');

        $this->applyScope($query);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (! empty($filters['property_id'])) {
            $query->where('property_id', (int) $filters['property_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $query->whereHas('tenant', fn ($q) => $q->where('first_name', 'like', $s)
                ->orWhere('last_name', 'like', $s));
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): TenantApplication
    {
        $query = TenantApplication::with(['tenant', 'property', 'unit', 'reviewedBy:id,name', 'screenedBy:id,name', 'documents']);
        $this->applyScope($query);

        return $query->findOrFail($id);
    }

    public function create(array $data): TenantApplication
    {
        $actor = $this->actor();
        $agencyId = $actor->isSuperAdmin() ? ($data['agency_id'] ?? null) : $actor->agency_id;
        if (! $agencyId) {
            abort(422, 'An application must belong to an agency.');
        }
        $this->ensureAgencyAccess($agencyId);

        $tenant = $this->resolveTenant((int) $data['tenant_id'], $agencyId);
        $property = $this->resolveProperty((int) $data['property_id'], $agencyId);
        $unit = null;
        if (! empty($data['unit_id'])) {
            $unit = $this->resolveUnit((int) $data['unit_id'], $property);
        }

        $application = DB::transaction(fn () => TenantApplication::create([
            'agency_id' => $agencyId,
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'unit_id' => $unit?->id,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]));

        $this->audit()->logModelChange('applications.create', $application);

        return $application->fresh();
    }

    /**
     * Move an application through its workflow.
     * Guards the transition map; approval requires a clear screening.
     */
    public function transition(TenantApplication $application, string $to, array $meta = []): TenantApplication
    {
        $this->ensureApplicationAccess($application);

        if (! $application->canTransitionTo($to)) {
            abort(422, "Cannot move an application from {$application->status} to {$to}.");
        }

        if ($to === 'approved' && $application->screening_status !== 'clear') {
            abort(422, 'An application cannot be approved without a clear screening.');
        }

        $old = $application->status;

        DB::transaction(function () use ($application, $to, $meta) {
            $application->update([
                'status' => $to,
                'reviewed_by' => in_array($to, ['approved', 'rejected'], true) ? $this->actor()?->id : $application->reviewed_by,
                'reviewed_at' => in_array($to, ['approved', 'rejected'], true) ? now() : $application->reviewed_at,
                'decision_notes' => $meta['decision_notes'] ?? $application->decision_notes,
            ]);
        });

        $this->audit()->log("applications.{$to}", $application, ['status' => $old]);

        return $application->fresh();
    }

    public function ensureApplicationAccess(TenantApplication $application): void
    {
        $this->ensureAgencyAccess($application->agency_id);
        app(TenantService::class)->ensureTenantAccess($application->tenant);
    }

    private function applyScope($query): void
    {
        $ids = TenantAccess::accessibleTenantIds($this->actor());
        if (! is_null($ids)) {
            $query->whereIn('tenant_id', $ids);
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

    private function resolveProperty(int $propertyId, int $agencyId): Property
    {
        return Property::withoutAgencyScope()
            ->where('id', $propertyId)
            ->where('agency_id', $agencyId)
            ->firstOrFail();
    }

    private function resolveUnit(int $unitId, Property $property): Unit
    {
        $unit = Unit::withoutAgencyScope()
            ->where('id', $unitId)
            ->where('property_id', $property->id)
            ->first();

        if (! $unit) {
            abort(422, 'The selected unit does not belong to the selected property.');
        }

        return $unit;
    }
}
