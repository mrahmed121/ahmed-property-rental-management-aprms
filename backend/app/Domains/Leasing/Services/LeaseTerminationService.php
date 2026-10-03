<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * LeaseTerminationService — controlled termination.
 * The lease record is never deleted; it becomes history.
 */
class LeaseTerminationService extends DomainService
{
    public function terminate(Lease $lease, string $terminationDate, string $reason): Lease
    {
        $leaseService = app(LeaseService::class);
        $leaseService->ensureLeaseAccess($lease);

        if ($lease->status !== 'active') {
            abort(422, 'Only active leases can be terminated.');
        }
        if (strtotime($terminationDate) < strtotime($lease->start_date)) {
            abort(422, 'The termination date cannot be before the lease start date.');
        }
        if (trim($reason) === '') {
            abort(422, 'A termination reason is required.');
        }

        return DB::transaction(function () use ($lease, $leaseService, $terminationDate, $reason) {
            $locked = Lease::where('id', $lease->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                abort(422, 'This lease is no longer active.');
            }

            $locked->update([
                'status' => 'terminated',
                'terminated_at' => now(),
                'termination_reason' => $reason,
                // A terminated lease ends on the termination date for occupancy math.
                'end_date' => min($locked->end_date->toDateString(), $terminationDate),
            ]);

            $leaseService->refreshUnitOccupancy($locked->unit_id);
            $leaseService->refreshTenantStatus($locked->tenant_id);

            $this->audit()->log('leases.terminate', $locked, ['reason' => $reason]);

            return $locked->fresh();
        });
    }
}
