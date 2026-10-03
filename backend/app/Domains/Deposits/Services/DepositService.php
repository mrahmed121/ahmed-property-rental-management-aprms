<?php

namespace App\Domains\Deposits\Services;

use App\Domains\Deposits\Models\Deposit;
use App\Domains\Deposits\Models\DepositTransaction;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * DepositService — the deposit lifecycle.
 *
 * Cap rule (approved): deposit_amount ≤ 3 × lease monthly_rent.
 * One deposit per lease (UNIQUE). History is preserved across
 * renewals/terminations via the lease link — never inferred.
 */
class DepositService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Deposit::with(['tenant:id,first_name,last_name', 'unit:id,unit_number', 'property:id,name'])
            ->orderByDesc('created_at');

        $this->applyScope($query);

        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['lease_id'])) $query->where('lease_id', $filters['lease_id']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', "%{$filters['search']}%")
                    ->orWhereHas('tenant', fn ($t) => $t->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Deposit
    {
        $deposit = Deposit::with([
            'tenant', 'lease', 'unit', 'property',
            'transactions.createdBy:id,name', 'deductions', 'settlement', 'createdBy:id,name',
        ])->findOrFail($id);
        $this->ensureDepositAccess($deposit);

        return $deposit;
    }

    /**
     * Create a deposit record for a lease. Enforces the 3× rent cap.
     */
    public function create(Lease $lease, array $data): Deposit
    {
        app(\App\Domains\Leasing\Services\LeaseService::class)->ensureLeaseAccess($lease);

        return DB::transaction(function () use ($lease, $data) {
            $existing = Deposit::where('agency_id', $lease->agency_id)
                ->where('lease_id', $lease->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort(422, 'A deposit already exists for this lease.');
            }

            $amount = round((float) $data['deposit_amount'], 2);
            if ($amount <= 0) {
                abort(422, 'Deposit amount must be positive.');
            }

            $cap = round((float) $lease->monthly_rent * 3, 2);
            if ($amount > $cap) {
                abort(422, "Deposit exceeds the approved cap of 3× monthly rent (₨".number_format($cap, 2).').');
            }

            $deposit = Deposit::create([
                'agency_id' => $lease->agency_id,
                'tenant_id' => $lease->tenant_id,
                'lease_id' => $lease->id,
                'unit_id' => $lease->unit_id,
                'property_id' => $lease->property_id,
                'deposit_amount' => $amount,
                'held_amount' => 0,
                'status' => 'required',
                'currency' => 'PKR',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->actor()?->id,
            ]);

            $this->audit()->log('deposits.create', $deposit, [
                'lease_id' => $lease->id, 'deposit_amount' => $amount,
            ]);

            return $deposit;
        });
    }

    /**
     * Record receipt of the deposit (money in hand).
     */
    public function receive(Deposit $deposit, array $data): Deposit
    {
        $this->ensureDepositAccess($deposit);

        return DB::transaction(function () use ($deposit, $data) {
            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            if (! in_array($deposit->status, ['required', 'held'], true)) {
                abort(422, 'Deposit cannot be received in its current status.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                abort(422, 'Received amount must be positive.');
            }

            $newHeld = round((float) $deposit->held_amount + $amount, 2);
            if ($newHeld > (float) $deposit->deposit_amount + 0.01) {
                abort(422, 'Received amount exceeds the agreed deposit.');
            }

            $deposit->update([
                'held_amount' => $newHeld,
                'status' => 'held',
                'received_date' => $deposit->received_date ?? ($data['received_date'] ?? now()->toDateString()),
            ]);

            $this->recordTransaction($deposit, 'received', $amount, $newHeld,
                $data['reason'] ?? 'Deposit received.');

            // Note: the deposit is tracked in the deposits table and its own
            // transaction history — NOT as a rent-ledger credit. It is a held
            // liability, not available to offset rent until settlement.

            $this->audit()->log('deposits.receive', $deposit, ['amount' => $amount]);

            return $deposit->fresh();
        });
    }

    /**
     * Adjust the agreed deposit amount (e.g. rent increased on renewal).
     * Still bound by the 3× cap against the current lease rent.
     */
    public function adjust(Deposit $deposit, array $data): Deposit
    {
        $this->ensureDepositAccess($deposit);

        return DB::transaction(function () use ($deposit, $data) {
            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->isSettled()) {
                abort(422, 'Settled deposits cannot be adjusted. Reverse the settlement first.');
            }

            $newAmount = round((float) $data['deposit_amount'], 2);
            if ($newAmount <= 0) {
                abort(422, 'Deposit amount must be positive.');
            }

            $cap = round((float) $deposit->lease->monthly_rent * 3, 2);
            if ($newAmount > $cap) {
                abort(422, 'Adjusted amount exceeds the 3× rent cap.');
            }

            $old = (float) $deposit->deposit_amount;
            $deposit->update(['deposit_amount' => $newAmount]);

            $this->recordTransaction(
                $deposit, 'adjustment', abs($newAmount - $old), (float) $deposit->held_amount,
                ($data['reason'] ?? 'Deposit adjusted.')." ({$old} → {$newAmount})"
            );

            $this->audit()->log('deposits.adjust', $deposit, [
                'from' => $old, 'to' => $newAmount,
            ]);

            return $deposit->fresh();
        });
    }

    /** Append a transaction row (history is append-only). */
    public function recordTransaction(
        Deposit $deposit,
        string $type,
        float $amount,
        float $balanceAfter,
        string $reason,
        array $opts = [],
    ): DepositTransaction {
        if (! in_array($type, DepositTransaction::TYPES, true)) {
            abort(422, "Invalid deposit transaction type: {$type}.");
        }
        if (trim($reason) === '') {
            abort(422, 'A reason is required for every deposit transaction.');
        }

        return DepositTransaction::create([
            'agency_id' => $deposit->agency_id,
            'deposit_id' => $deposit->id,
            'type' => $type,
            'amount' => round($amount, 2),
            'balance_after' => round($balanceAfter, 2),
            'reason' => $reason,
            'reference_type' => $opts['reference_type'] ?? null,
            'reference_id' => $opts['reference_id'] ?? null,
            'created_by' => $this->actor()?->id,
        ]);
    }

    public function ensureDepositAccess(Deposit $deposit): void
    {
        $this->ensureAgencyAccess($deposit->agency_id);

        $actor = $this->actor();
        if ($actor && $actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            if (! in_array($deposit->tenant_id, $tenantIds ?? [], true)) {
                abort(404);
            }
        }
        if ($actor && $actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds) && ! in_array($deposit->property_id, $propertyIds, true)) {
                abort(404);
            }
        }
        if ($actor && $actor->hasRole('technician')) {
            abort(404);
        }
    }

    private function applyScope($query): void
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

        $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
        if (is_array($propertyIds)) {
            $query->whereIn('property_id', $propertyIds);
        }
    }
}
