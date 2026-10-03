<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * LeaseRenewalService — renewals never overwrite history.
 * A renewal creates a SUCCESSOR draft lease linked via previous_lease_id;
 * the old lease flips to "renewed" only when the successor activates.
 */
class LeaseRenewalService extends DomainService
{
    public function renew(Lease $lease, array $data): Lease
    {
        app(LeaseService::class)->ensureLeaseAccess($lease);

        if ($lease->status !== 'active') {
            abort(422, 'Only active leases can be renewed.');
        }
        if ($lease->successorLease) {
            abort(422, 'This lease already has a renewal in progress.');
        }

        $start = $data['start_date'];
        $end = $data['end_date'];

        if (strtotime($end) <= strtotime($start)) {
            abort(422, 'The renewal end date must be after its start date.');
        }
        if (strtotime($start) < strtotime($lease->end_date)) {
            abort(422, 'The renewal cannot start before the current lease ends.');
        }

        $successor = DB::transaction(function () use ($lease, $data) {
            $actor = $this->actor();

            $new = Lease::create([
                'agency_id' => $lease->agency_id,
                'tenant_id' => $lease->tenant_id,
                'unit_id' => $lease->unit_id,
                'property_id' => $lease->property_id,
                'building_id' => $lease->building_id,
                'application_id' => $lease->application_id,
                'previous_lease_id' => $lease->id,
                'lease_number' => 'pending',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? $lease->deposit_amount,
                'status' => 'draft',
                'terms' => $data['terms'] ?? $lease->terms,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $new->update([
                'lease_number' => sprintf('LSE-%d-%s-%06d', $lease->agency_id, now()->format('Y'), $new->id),
            ]);

            return $new;
        });

        $this->audit()->log('leases.renew', $successor, ['previous_lease_id' => $lease->id]);

        return $successor->fresh();
    }
}
