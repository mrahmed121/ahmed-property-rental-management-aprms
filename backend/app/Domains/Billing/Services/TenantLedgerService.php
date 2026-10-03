<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\TenantLedgerEntry;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * TenantLedgerService — the single write path for tenant financial activity.
 *
 * The running balance is DERIVED: balance_after = previous balance + debit - credit.
 * Nothing stores an editable "current balance". Corrections use new entries
 * (adjustment/reversal), never mutations of posted rows.
 */
class TenantLedgerService extends DomainService
{
    /**
     * Post a ledger entry inside the caller's transaction (or its own).
     * Returns the created entry.
     */
    public function post(
        Tenant $tenant,
        string $entryType,
        float $debit,
        float $credit,
        string $description,
        array $opts = [],
    ): TenantLedgerEntry {
        if (! in_array($entryType, TenantLedgerEntry::TYPES, true)) {
            abort(422, "Invalid ledger entry type: {$entryType}.");
        }
        $debit = round($debit, 2);
        $credit = round($credit, 2);
        if ($debit < 0 || $credit < 0) {
            abort(422, 'Ledger amounts cannot be negative.');
        }
        if ($debit == 0 && $credit == 0 && $entryType !== 'allocation') {
            abort(422, 'Ledger entry must move money.');
        }

        return DB::transaction(function () use ($tenant, $entryType, $debit, $credit, $description, $opts) {
            // Lock the tenant's latest entry to serialize balance computation.
            $last = TenantLedgerEntry::where('tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $balanceAfter = round((float) ($last?->balance_after ?? 0) + $debit - $credit, 2);

            $entry = TenantLedgerEntry::create([
                'agency_id' => $tenant->agency_id,
                'tenant_id' => $tenant->id,
                'lease_id' => $opts['lease_id'] ?? null,
                'entry_type' => $entryType,
                'reference_type' => $opts['reference_type'] ?? null,
                'reference_id' => $opts['reference_id'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $balanceAfter,
                'entry_date' => $opts['entry_date'] ?? now()->toDateString(),
                'description' => $description,
                'created_by' => $this->actor()?->id,
            ]);

            $this->audit()->log('ledger.post', $entry, [
                'entry_type' => $entryType, 'debit' => $debit,
                'credit' => $credit, 'balance_after' => $balanceAfter,
            ]);

            return $entry;
        });
    }

    /** Current derived balance for a tenant (sum of debits - credits). */
    public function balance(Tenant $tenant): float
    {
        $last = TenantLedgerEntry::where('tenant_id', $tenant->id)
            ->orderByDesc('id')->first();

        return round((float) ($last?->balance_after ?? 0), 2);
    }

    /**
     * Full ledger view: opening balance, entries, closing balance.
     * Paginated entries; opening/closing computed from the same rows.
     */
    public function statement(Tenant $tenant, array $filters = []): array
    {
        $query = TenantLedgerEntry::where('tenant_id', $tenant->id)
            ->with('createdBy:id,name')
            ->orderBy('entry_date')
            ->orderBy('id');

        if (! empty($filters['from'])) $query->where('entry_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->where('entry_date', '<=', $filters['to']);
        if (! empty($filters['entry_type'])) $query->where('entry_type', $filters['entry_type']);

        $entries = $query->get();

        $opening = 0;
        if (! empty($filters['from'])) {
            $prior = TenantLedgerEntry::where('tenant_id', $tenant->id)
                ->where('entry_date', '<', $filters['from'])
                ->orderByDesc('entry_date')->orderByDesc('id')->first();
            $opening = round((float) ($prior?->balance_after ?? 0), 2);
        }

        $totalDebit = round($entries->sum('debit'), 2);
        $totalCredit = round($entries->sum('credit'), 2);
        $closing = $entries->isNotEmpty()
            ? round((float) $entries->last()->balance_after, 2)
            : $opening;

        return [
            'tenant_id' => $tenant->id,
            'opening_balance' => $opening,
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $closing,
        ];
    }
}
