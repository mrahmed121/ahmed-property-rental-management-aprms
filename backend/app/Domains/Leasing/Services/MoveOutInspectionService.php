<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\MoveOutInspection;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

class MoveOutInspectionService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = MoveOutInspection::with(['lease:id,lease_number,tenant_id', 'lease.tenant:id,first_name,last_name'])
            ->orderByDesc('id');

        $this->applyScope($query);

        if (! empty($filters['lease_id'])) {
            $query->where('lease_id', (int) $filters['lease_id']);
        }
        if (! empty($filters['review_status'])) {
            $query->where('review_status', $filters['review_status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): MoveOutInspection
    {
        $query = MoveOutInspection::with(['lease.tenant', 'lease.unit', 'reviewedBy:id,name', 'documents']);
        $this->applyScope($query);

        return $query->findOrFail($id);
    }

    /** Record a move-out inspection. One per lease; lease must be terminated or expired. */
    public function create(int $leaseId, array $data): MoveOutInspection
    {
        $lease = $this->resolveLease($leaseId);

        if (! in_array($lease->status, ['terminated', 'expired'], true)) {
            abort(422, 'A move-out inspection requires a terminated or expired lease.');
        }
        if ($lease->inspection) {
            abort(422, 'This lease already has a move-out inspection.');
        }

        $inspection = DB::transaction(fn () => MoveOutInspection::create([
            'agency_id' => $lease->agency_id,
            'lease_id' => $lease->id,
            ...$data,
        ]));

        $this->audit()->logModelChange('inspections.create', $inspection);

        return $inspection->fresh();
    }

    public function update(MoveOutInspection $inspection, array $data): MoveOutInspection
    {
        $this->ensureInspectionAccess($inspection);

        $old = $inspection->toArray();
        $inspection->update($data);

        $this->audit()->log('inspections.update', $inspection, $old);

        return $inspection->fresh();
    }

    /** Mark the inspection reviewed. Deposit settlement itself belongs to P4+. */
    public function review(MoveOutInspection $inspection): MoveOutInspection
    {
        $this->ensureInspectionAccess($inspection);

        $inspection->update([
            'review_status' => 'reviewed',
            'reviewed_by' => $this->actor()?->id,
            'reviewed_at' => now(),
        ]);

        $this->audit()->log('inspections.review', $inspection);

        return $inspection->fresh();
    }

    public function ensureInspectionAccess(MoveOutInspection $inspection): void
    {
        $this->ensureAgencyAccess($inspection->agency_id);
        app(LeaseService::class)->ensureLeaseAccess($inspection->lease);
    }

    private function applyScope($query): void
    {
        $ids = TenantAccess::accessibleTenantIds($this->actor());
        if (! is_null($ids)) {
            $query->whereHas('lease', fn ($q) => $q->whereIn('tenant_id', $ids));
        }
    }

    private function resolveLease(int $leaseId): Lease
    {
        $lease = Lease::with('inspection')->find($leaseId);
        if (! $lease) {
            abort(404);
        }
        app(LeaseService::class)->ensureLeaseAccess($lease);

        return $lease;
    }
}
