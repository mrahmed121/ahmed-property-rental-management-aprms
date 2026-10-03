<?php

namespace App\Domains\Statements\Services;

use App\Domains\Billing\Models\TenantLedgerEntry;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Maintenance\Models\MaintenanceQuote;
use App\Domains\Property\Models\Property;
use App\Domains\Shared\Models\Setting;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Services\DomainService;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementPeriod;
use App\Domains\Utilities\Models\UtilityAllocation;
use Illuminate\Support\Facades\DB;

/**
 * StatementGenerationService — builds owner statements from real data.
 *
 * INCOME BASIS (documented): ACCRUAL.
 * Income = sum of P4 tenant-ledger `invoice` debits (rent charged) for the
 * owner's properties within the period. Payments are NOT income — they are
 * credits that settle invoices. This prevents double-counting invoice
 * amounts and payment amounts as two separate incomes.
 *
 * Components:
 *  gross_income            = invoice debits (accrual)
 *  management_fee          = gross_income × agency fee % (from settings)
 *  owner_expenses          = posted P6 expenses
 *  owner_maintenance       = approved owner-attributed P5 quotes (excluding
 *                            those already represented as posted expenses)
 *  owner_utility_absorption= finalized P6 vacant_owner allocations
 *  adjustments_total       = signed statement adjustments
 *  net = income − fee − expenses − maintenance − utility + adjustments
 */
class StatementGenerationService extends DomainService
{
    /**
     * Preview: full calculation without persisting.
     */
    public function preview(User $owner, StatementPeriod $period): array
    {
        $this->ensureOwnerInAgency($owner, $period->agency_id);
        $propertyIds = $this->ownerPropertyIds($owner);

        return $this->calculate($owner, $period, $propertyIds);
    }

    /**
     * Generate (or return existing) statement for owner + period.
     */
    public function generate(User $owner, StatementPeriod $period): OwnerStatement
    {
        $this->ensureOwnerInAgency($owner, $period->agency_id);

        if ($period->isLocked()) {
            abort(422, 'Statement period is locked.');
        }

        return DB::transaction(function () use ($owner, $period) {
            // Lock the period row to prevent concurrent generation.
            $period = StatementPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            $existing = OwnerStatement::where('agency_id', $period->agency_id)
                ->where('owner_id', $owner->id)
                ->where('statement_period_id', $period->id)
                ->first();

            if ($existing) {
                return $existing; // idempotent — no duplicates
            }

            $propertyIds = $this->ownerPropertyIds($owner);
            $calc = $this->calculate($owner, $period, $propertyIds);

            $statement = OwnerStatement::create([
                'agency_id' => $period->agency_id,
                'statement_period_id' => $period->id,
                'owner_id' => $owner->id,
                'statement_number' => $this->nextStatementNumber($period->agency_id),
                'currency' => 'PKR',
                'status' => 'draft',
                'gross_income' => $calc['gross_income'],
                'management_fee_percent' => $calc['management_fee_percent'],
                'management_fee' => $calc['management_fee'],
                'owner_expenses' => $calc['owner_expenses'],
                'owner_maintenance' => $calc['owner_maintenance'],
                'owner_utility_absorption' => $calc['owner_utility_absorption'],
                'adjustments_total' => 0,
                'net_amount' => $calc['net_amount'],
                'generated_by' => $this->actor()?->id,
            ]);

            // Persist traceable lines.
            foreach ($calc['lines'] as $line) {
                $statement->lines()->create([
                    'agency_id' => $period->agency_id,
                    'line_type' => $line['line_type'],
                    'source_type' => $line['source_type'] ?? null,
                    'source_id' => $line['source_id'] ?? null,
                    'property_id' => $line['property_id'] ?? null,
                    'unit_id' => $line['unit_id'] ?? null,
                    'description' => $line['description'],
                    'line_date' => $line['line_date'],
                    'amount' => $line['amount'],
                    'reference' => $line['reference'] ?? null,
                ]);
            }

            $this->audit()->log('statements.generate', $statement, [
                'statement_number' => $statement->statement_number,
                'net_amount' => (float) $statement->net_amount,
            ]);

            return $statement->fresh();
        });
    }

    /**
     * Core calculation. Returns totals + traceable lines.
     */
    public function calculate(User $owner, StatementPeriod $period, array $propertyIds): array
    {
        $agencyId = $period->agency_id;
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $lines = [];

        // --- 1. Income (accrual): invoice debits ---
        $incomeEntries = TenantLedgerEntry::where('agency_id', $agencyId)
            ->where('entry_type', 'invoice')
            ->whereDate('entry_date', '>=', $start)
            ->whereDate('entry_date', '<=', $end)
            ->whereHas('lease', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->with(['lease.property:id,name', 'lease.unit:id,unit_number'])
            ->get();

        $grossIncome = 0;
        foreach ($incomeEntries as $e) {
            $grossIncome = round($grossIncome + (float) $e->debit, 2);
            $lines[] = [
                'line_type' => 'income',
                'source_type' => TenantLedgerEntry::class,
                'source_id' => $e->id,
                'property_id' => $e->lease?->property_id,
                'unit_id' => $e->lease?->unit_id,
                'description' => $e->description,
                'line_date' => $e->entry_date->toDateString(),
                'amount' => round((float) $e->debit, 2),
                'reference' => "LEDGER-{$e->id}",
            ];
        }

        // --- 2. Management fee: % of gross income (agency setting) ---
        $feePercent = $this->managementFeePercent($agencyId);
        $fee = round($grossIncome * $feePercent / 100, 2);
        if ($fee > 0) {
            $lines[] = [
                'line_type' => 'management_fee',
                'description' => "Management fee {$feePercent}% of gross income",
                'line_date' => $end,
                'amount' => -$fee,
                'reference' => "FEE-{$feePercent}PCT",
            ];
        }

        // --- 3. Owner expenses: posted P6 expenses ---
        $expenses = Expense::where('agency_id', $agencyId)
            ->whereIn('property_id', $propertyIds)
            ->where('status', 'posted')
            ->whereDate('expense_date', '>=', $start)
            ->whereDate('expense_date', '<=', $end)
            ->with(['property:id,name', 'unit:id,unit_number'])
            ->get();

        $ownerExpenses = 0;
        foreach ($expenses as $ex) {
            $ownerExpenses = round($ownerExpenses + (float) $ex->amount, 2);
            $lines[] = [
                'line_type' => 'expense',
                'source_type' => Expense::class,
                'source_id' => $ex->id,
                'property_id' => $ex->property_id,
                'unit_id' => $ex->unit_id,
                'description' => "Expense {$ex->expense_number}: {$ex->description}",
                'line_date' => $ex->expense_date->toDateString(),
                'amount' => -round((float) $ex->amount, 2),
                'reference' => $ex->expense_number,
            ];
        }

        // --- 4. Owner maintenance: approved owner-attributed quotes ---
        $quotes = MaintenanceQuote::where('agency_id', $agencyId)
            ->where('attribution', 'owner')
            ->where('status', 'approved')
            ->whereHas('ticket', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->with(['ticket.property:id,name', 'ticket.unit:id,unit_number'])
            ->get();

        $ownerMaintenance = 0;
        foreach ($quotes as $q) {
            // Avoid double-count: skip if already represented as a posted expense
            // (same vendor + same amount + date within 7 days).
            $approvedAt = $q->updated_at->toDateString();
            $duplicate = Expense::where('agency_id', $agencyId)
                ->where('status', 'posted')
                ->where('vendor_id', $q->vendor_id)
                ->where('amount', (float) $q->total)
                ->whereDate('expense_date', '>=', date('Y-m-d', strtotime($approvedAt.' -7 days')))
                ->whereDate('expense_date', '<=', date('Y-m-d', strtotime($approvedAt.' +7 days')))
                ->exists();

            if ($duplicate) continue;

            $ownerMaintenance = round($ownerMaintenance + (float) $q->total, 2);
            $lines[] = [
                'line_type' => 'maintenance',
                'source_type' => MaintenanceQuote::class,
                'source_id' => $q->id,
                'property_id' => $q->ticket?->property_id,
                'unit_id' => $q->ticket?->unit_id,
                'description' => "Maintenance (owner): {$q->ticket?->ticket_number} — ".substr($q->description ?? '', 0, 80),
                'line_date' => $approvedAt,
                'amount' => -round((float) $q->total, 2),
                'reference' => "QUOTE-{$q->id}",
            ];
        }

        // --- 5. Owner utility absorption: finalized vacant_owner allocations ---
        $allocs = UtilityAllocation::where('agency_id', $agencyId)
            ->where('allocation_type', 'vacant_owner')
            ->whereHas('bill', function ($q) use ($start, $end, $propertyIds) {
                $q->where('status', 'finalized')
                    ->whereDate('period_end', '>=', $start)
                    ->whereDate('period_end', '<=', $end)
                    ->whereIn('property_id', $propertyIds);
            })
            ->with(['bill.meter:id,utility_type', 'bill.property:id,name'])
            ->get();

        $ownerUtility = 0;
        foreach ($allocs as $a) {
            $ownerUtility = round($ownerUtility + (float) $a->amount, 2);
            $lines[] = [
                'line_type' => 'utility',
                'source_type' => UtilityAllocation::class,
                'source_id' => $a->id,
                'property_id' => $a->bill?->property_id,
                'unit_id' => $a->unit_id,
                'description' => "Owner-absorbed utility ({$a->bill?->meter?->utility_type}): {$a->bill?->bill_number}",
                'line_date' => $a->bill?->period_end?->toDateString() ?? $end,
                'amount' => -round((float) $a->amount, 2),
                'reference' => $a->bill?->bill_number,
            ];
        }

        $net = OwnerStatement::reconcile(
            $grossIncome, $fee, $ownerExpenses, $ownerMaintenance, $ownerUtility, 0
        );

        return [
            'gross_income' => round($grossIncome, 2),
            'management_fee_percent' => $feePercent,
            'management_fee' => $fee,
            'owner_expenses' => round($ownerExpenses, 2),
            'owner_maintenance' => round($ownerMaintenance, 2),
            'owner_utility_absorption' => round($ownerUtility, 2),
            'adjustments_total' => 0,
            'net_amount' => $net,
            'lines' => $lines,
            'line_count' => count($lines),
        ];
    }

    /** Management fee % from agency settings (default 10). */
    public function managementFeePercent(int $agencyId): float
    {
        $setting = Setting::where('agency_id', $agencyId)
            ->where('key', 'management_fee_percent')
            ->first();

        if ($setting) {
            $v = (float) $setting->value;
            if ($v >= 0 && $v <= 100) return round($v, 2);
        }

        return 10.0;
    }

    /** Properties owned by this owner (owner_id → User). */
    public function ownerPropertyIds(User $owner): array
    {
        return Property::where('agency_id', $owner->agency_id)
            ->where('owner_id', $owner->id)
            ->pluck('id')
            ->all();
    }

    private function ensureOwnerInAgency(User $owner, int $agencyId): void
    {
        if ((int) $owner->agency_id !== (int) $agencyId) {
            abort(404);
        }
    }

    private function nextStatementNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = OwnerStatement::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('statement_number', 'like', "STMT-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->statement_number, -6)) + 1 : 1;

        return sprintf('STMT-%s-%06d', $year, $seq);
    }
}
