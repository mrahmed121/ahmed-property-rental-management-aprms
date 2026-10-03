<?php

namespace App\Domains\Utilities\Services;

use App\Domains\Billing\Services\TenantLedgerService;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Services\DomainService;
use App\Domains\Utilities\Models\UtilityAllocation;
use App\Domains\Utilities\Models\UtilityBill;
use App\Domains\Utilities\Models\UtilityMeter;
use Illuminate\Support\Facades\DB;

/**
 * UtilityBillingService — bills, allocation, and P4 ledger integration.
 *
 * - Bills are generated from meter readings (deterministic).
 * - One finalized bill per (meter, period) — UNIQUE + row lock.
 * - Tenant charges post through the P4 TenantLedgerService so they
 *   participate in the existing payment waterfall (utilities bucket).
 * - Vacant-unit shares are absorbed by the owner as a DISTINCT line item
 *   (allocation_type = vacant_owner), never hidden.
 * - Posted bills are reversed, never hard-deleted or casually edited.
 */
class UtilityBillingService extends DomainService
{
    public function listBills(array $filters = [])
    {
        $query = UtilityBill::with([
            'meter:id,meter_number,utility_type', 'property:id,name',
            'unit:id,unit_number', 'tenant:id,first_name,last_name',
        ])->orderByDesc('period_start');

        $this->applyBillScope($query);

        if (! empty($filters['meter_id'])) $query->where('meter_id', $filters['meter_id']);
        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);
        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['utility_type'])) {
            $query->whereHas('meter', fn ($q) => $q->where('utility_type', $filters['utility_type']));
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findBill(int $id): UtilityBill
    {
        $bill = UtilityBill::with([
            'meter', 'property', 'building', 'unit', 'tenant', 'lease',
            'allocations.unit:id,unit_number', 'allocations.tenant:id,first_name,last_name',
            'createdBy:id,name', 'finalizedBy:id,name',
        ])->findOrFail($id);
        app(UtilityMeterService::class)->ensureMeterAccess($bill->meter);

        return $bill;
    }

    /**
     * Generate a draft bill for a meter and period.
     */
    public function generate(UtilityMeter $meter, array $data): UtilityBill
    {
        app(UtilityMeterService::class)->ensureMeterAccess($meter);

        return DB::transaction(function () use ($meter, $data) {
            $meter = UtilityMeter::where('id', $meter->id)->lockForUpdate()->firstOrFail();

            $periodStart = $data['period_start'];
            $periodEnd = $data['period_end'];
            if ($periodEnd < $periodStart) {
                abort(422, 'Billing period end cannot precede start.');
            }

            // Duplicate finalized bill guard.
            if (UtilityBill::where('agency_id', $meter->agency_id)
                ->where('meter_id', $meter->id)
                ->whereDate('period_start', $periodStart)
                ->where('status', 'finalized')->exists()) {
                abort(422, 'A finalized bill already exists for this meter and period.');
            }

            $consumption = app(UtilityMeterService::class)->consumption(
                $meter, $periodStart, $periodEnd
            );

            $rate = round((float) $data['rate'], 4);
            $fixed = round((float) ($data['fixed_charge'] ?? 0), 2);
            $tax = round((float) ($data['tax_amount'] ?? 0), 2);

            $calc = UtilityBill::calculate($consumption['consumption'], $rate, $fixed, $tax);

            // Resolve tenant/lease for unit meters.
            $tenantId = null;
            $leaseId = null;
            if ($meter->unit_id) {
                $lease = Lease::where('unit_id', $meter->unit_id)
                    ->where('agency_id', $meter->agency_id)
                    ->where('status', 'active')
                    ->whereDate('start_date', '<=', $periodEnd)
                    ->whereDate('end_date', '>=', $periodStart)
                    ->first();
                if ($lease) {
                    $tenantId = $lease->tenant_id;
                    $leaseId = $lease->id;
                }
            }

            $bill = UtilityBill::create([
                'agency_id' => $meter->agency_id,
                'meter_id' => $meter->id,
                'property_id' => $meter->property_id,
                'building_id' => $meter->building_id,
                'unit_id' => $meter->unit_id,
                'tenant_id' => $tenantId,
                'lease_id' => $leaseId,
                'bill_number' => $this->nextBillNumber($meter->agency_id),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'previous_reading' => $consumption['previous_reading'],
                'current_reading' => $consumption['current_reading'],
                'consumption' => $calc['consumption'],
                'rate' => $rate,
                'fixed_charge' => $calc['fixed_charge'],
                'tax_amount' => $calc['tax_amount'],
                'total' => $calc['total'],
                'currency' => 'PKR',
                'status' => 'draft',
                'allocation_method' => $data['allocation_method'] ?? 'metered',
                'created_by' => $this->actor()?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Default allocation: single metered line.
            $this->allocate($bill, [[
                'unit_id' => $meter->unit_id,
                'tenant_id' => $tenantId,
                'allocation_type' => $tenantId ? 'metered' : 'vacant_owner',
                'consumption_share' => $calc['consumption'],
                'amount' => $calc['total'],
                'notes' => $tenantId
                    ? 'Metered consumption charged to tenant.'
                    : 'Vacant unit — share absorbed by owner as a distinct line item.',
            ]]);

            $this->audit()->log('utilities.bill_generate', $bill, [
                'bill_number' => $bill->bill_number,
                'total' => $calc['total'],
            ]);

            return $bill->fresh();
        });
    }

    /**
     * Replace a draft bill's allocations (e.g. shared-utility splits).
     */
    public function allocate(UtilityBill $bill, array $lines): UtilityBill
    {
        return DB::transaction(function () use ($bill, $lines) {
            $bill = UtilityBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->isFinalized()) {
                abort(422, 'Finalized bills cannot be re-allocated. Reverse first.');
            }

            $total = 0;
            foreach ($lines as $line) {
                if (! in_array($line['allocation_type'], UtilityAllocation::TYPES, true)) {
                    abort(422, 'Invalid allocation type.');
                }
                $amount = round((float) $line['amount'], 2);
                if ($amount < 0) {
                    abort(422, 'Allocation amounts cannot be negative.');
                }
                $total = round($total + $amount, 2);
            }

            // Allocations must sum to the bill total.
            if (abs($total - (float) $bill->total) > 0.01) {
                abort(422, "Allocations (₨".number_format($total, 2).") must sum to the bill total (₨".number_format($bill->total, 2).").");
            }

            $bill->allocations()->delete();
            foreach ($lines as $line) {
                $bill->allocations()->create([
                    'agency_id' => $bill->agency_id,
                    'unit_id' => $line['unit_id'] ?? null,
                    'tenant_id' => $line['tenant_id'] ?? null,
                    'allocation_type' => $line['allocation_type'],
                    'consumption_share' => round((float) ($line['consumption_share'] ?? 0), 2),
                    'amount' => round((float) $line['amount'], 2),
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $this->audit()->log('utilities.bill_allocate', $bill, [
                'lines' => count($lines),
            ]);

            return $bill->fresh();
        });
    }

    /**
     * Finalize a bill. Tenant allocations post to the P4 ledger
     * (utilities bucket in the waterfall). Owner-absorbed lines do not
     * charge any tenant.
     */
    public function finalize(UtilityBill $bill): UtilityBill
    {
        app(UtilityMeterService::class)->ensureMeterAccess($bill->meter);

        return DB::transaction(function () use ($bill) {
            $bill = UtilityBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->isFinalized()) {
                return $bill; // idempotent
            }
            if ($bill->status === 'reversed') {
                abort(422, 'Reversed bills cannot be finalized.');
            }

            // Duplicate guard (UNIQUE also protects).
            if (UtilityBill::where('agency_id', $bill->agency_id)
                ->where('meter_id', $bill->meter_id)
                ->whereDate('period_start', $bill->period_start)
                ->where('status', 'finalized')
                ->where('id', '!=', $bill->id)->exists()) {
                abort(422, 'Another finalized bill exists for this meter and period.');
            }

            $ledger = app(TenantLedgerService::class);

            foreach ($bill->allocations as $alloc) {
                // Vacant-owner lines: recorded, never charged to a tenant.
                if ($alloc->isOwnerAbsorbed() || ! $alloc->tenant_id) {
                    continue;
                }

                $tenant = $alloc->tenant;
                if (! $tenant) continue;

                // Post as a utility charge — the P4 waterfall recognizes
                // the utilities bucket for payment allocation.
                $ledger->post(
                    $tenant, 'utility', (float) $alloc->amount, 0,
                    "Utility bill {$bill->bill_number} ({$bill->meter->utility_type})",
                    [
                        'lease_id' => $bill->lease_id,
                        'reference_type' => UtilityBill::class,
                        'reference_id' => $bill->id,
                        'entry_date' => $bill->period_end->toDateString(),
                        'meta' => [
                            'utility_bill_id' => $bill->id,
                            'allocation_id' => $alloc->id,
                            'utility_type' => $bill->meter->utility_type,
                        ],
                    ]
                );
            }

            $bill->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $this->actor()?->id,
            ]);

            $this->audit()->log('utilities.bill_finalize', $bill, [
                'bill_number' => $bill->bill_number,
                'total' => (float) $bill->total,
            ]);

            return $bill->fresh();
        });
    }

    /**
     * Reverse a finalized bill (correction path). Posts compensating
     * ledger entries; never hard-deletes.
     */
    public function reverse(UtilityBill $bill, string $reason): UtilityBill
    {
        app(UtilityMeterService::class)->ensureMeterAccess($bill->meter);

        return DB::transaction(function () use ($bill, $reason) {
            $bill = UtilityBill::where('id', $bill->id)->lockForUpdate()->firstOrFail();

            if (! $bill->isFinalized()) {
                abort(422, 'Only finalized bills can be reversed.');
            }
            if (trim($reason) === '') {
                abort(422, 'A reason is required.');
            }

            $ledger = app(TenantLedgerService::class);

            foreach ($bill->allocations as $alloc) {
                if ($alloc->isOwnerAbsorbed() || ! $alloc->tenant_id) {
                    continue;
                }
                $tenant = $alloc->tenant;
                if (! $tenant) continue;

                $ledger->post(
                    $tenant, 'reversal', 0, (float) $alloc->amount,
                    "Reversed utility bill {$bill->bill_number}: {$reason}",
                    [
                        'lease_id' => $bill->lease_id,
                        'reference_type' => UtilityBill::class,
                        'reference_id' => $bill->id,
                        'meta' => ['reversal_of' => $bill->id],
                    ]
                );
            }

            $bill->update(['status' => 'reversed']);

            $this->audit()->log('utilities.bill_reverse', $bill, ['reason' => $reason]);

            return $bill->fresh();
        });
    }

    /** Preview: consumption + bill math + allocation, no writes. */
    public function preview(UtilityMeter $meter, array $data): array
    {
        app(UtilityMeterService::class)->ensureMeterAccess($meter);

        $consumption = app(UtilityMeterService::class)->consumption(
            $meter, $data['period_start'], $data['period_end']
        );
        $calc = UtilityBill::calculate(
            $consumption['consumption'],
            round((float) $data['rate'], 4),
            round((float) ($data['fixed_charge'] ?? 0), 2),
            round((float) ($data['tax_amount'] ?? 0), 2)
        );

        return $consumption + $calc + [
            'rate' => round((float) $data['rate'], 4),
            'currency' => 'PKR',
        ];
    }

    private function applyBillScope($query): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            $query->whereRaw('1 = 0');
            return;
        }

        $tenantIds = TenantAccess::accessibleTenantIds($actor);
        if (is_array($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }
        }
    }

    private function nextBillNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = UtilityBill::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('bill_number', 'like', "UB-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->bill_number, -6)) + 1 : 1;

        return sprintf('UB-%s-%06d', $year, $seq);
    }
}
