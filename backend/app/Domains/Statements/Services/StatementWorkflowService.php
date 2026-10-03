<?php

namespace App\Domains\Statements\Services;

use App\Domains\Shared\Services\DomainService;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementAdjustment;
use App\Domains\Statements\Models\StatementPeriod;
use Illuminate\Support\Facades\DB;

/**
 * StatementWorkflowService — review, approval, finalization, adjustments,
 * period locking. Finalized statements are immutable.
 */
class StatementWorkflowService extends DomainService
{
    public function transition(OwnerStatement $statement, string $to): OwnerStatement
    {
        $this->ensureStatementAccess($statement);

        return DB::transaction(function () use ($statement, $to) {
            $statement = OwnerStatement::where('id', $statement->id)->lockForUpdate()->firstOrFail();

            if (! OwnerStatement::canTransition($statement->status, $to)) {
                abort(422, "Cannot move statement from {$statement->status} to {$to}.");
            }

            if ($statement->period->isLocked()) {
                abort(422, 'Statement period is locked.');
            }

            $from = $statement->status;
            $actor = $this->actor();
            $statement->status = $to;

            if ($to === 'approved') {
                $statement->approved_by = $actor?->id;
                $statement->approved_at = now();
            }
            if ($to === 'finalized') {
                $statement->finalized_by = $actor?->id;
                $statement->finalized_at = now();
            }
            $statement->save();

            $this->audit()->log('statements.transition', $statement, [
                'from' => $from, 'to' => $to,
            ]);

            return $statement->fresh();
        });
    }

    /**
     * Add an adjustment to a non-finalized statement.
     * Recalculates totals deterministically.
     */
    public function adjust(OwnerStatement $statement, float $amount, string $reason): OwnerStatement
    {
        $this->ensureStatementAccess($statement);

        return DB::transaction(function () use ($statement, $amount, $reason) {
            $statement = OwnerStatement::where('id', $statement->id)->lockForUpdate()->firstOrFail();

            if ($statement->isFinalized()) {
                abort(422, 'Finalized statements cannot be adjusted. Use the correction workflow.');
            }
            if ($statement->period->isLocked()) {
                abort(422, 'Statement period is locked.');
            }
            if (trim($reason) === '') {
                abort(422, 'A reason is required.');
            }
            if ($amount == 0) {
                abort(422, 'Adjustment amount cannot be zero.');
            }

            $adjustment = StatementAdjustment::create([
                'agency_id' => $statement->agency_id,
                'owner_statement_id' => $statement->id,
                'amount' => round($amount, 2),
                'reason' => trim($reason),
                'created_by' => $this->actor()?->id,
            ]);

            $statement->lines()->create([
                'agency_id' => $statement->agency_id,
                'line_type' => 'adjustment',
                'source_type' => StatementAdjustment::class,
                'source_id' => $adjustment->id,
                'description' => "Adjustment: {$adjustment->reason}",
                'line_date' => now()->toDateString(),
                'amount' => round($amount, 2),
                'reference' => "ADJ-{$adjustment->id}",
            ]);

            $this->recalculate($statement);

            $this->audit()->log('statements.adjust', $statement, [
                'amount' => round($amount, 2),
                'reason' => $reason,
            ]);

            return $statement->fresh();
        });
    }

    /** Recompute totals from lines (deterministic, no manual totals). */
    public function recalculate(OwnerStatement $statement): OwnerStatement
    {
        return DB::transaction(function () use ($statement) {
            $statement = OwnerStatement::where('id', $statement->id)->lockForUpdate()->firstOrFail();

            if ($statement->isFinalized()) {
                abort(422, 'Finalized statements are immutable.');
            }

            $lines = $statement->lines()->get();

            $sum = fn ($type) => round($lines->where('line_type', $type)->sum('amount'), 2);

            // Income lines are positive; cost lines are negative.
            $grossIncome = $sum('income');
            $fee = abs($sum('management_fee'));
            $expenses = abs($sum('expense'));
            $maintenance = abs($sum('maintenance'));
            $utility = abs($sum('utility'));
            $adjustments = $sum('adjustment');

            $statement->gross_income = $grossIncome;
            $statement->management_fee = $fee;
            $statement->owner_expenses = $expenses;
            $statement->owner_maintenance = $maintenance;
            $statement->owner_utility_absorption = $utility;
            $statement->adjustments_total = $adjustments;
            $statement->net_amount = OwnerStatement::reconcile(
                $grossIncome, $fee, $expenses, $maintenance, $utility, $adjustments
            );
            $statement->save();

            $this->audit()->log('statements.recalculate', $statement, [
                'net_amount' => (float) $statement->net_amount,
            ]);

            return $statement->fresh();
        });
    }

    public function lockPeriod(StatementPeriod $period): StatementPeriod
    {
        return DB::transaction(function () use ($period) {
            $period = StatementPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            if ($period->isLocked()) {
                return $period; // idempotent
            }

            // All statements in the period must be finalized before locking.
            $open = $period->statements()->where('status', '!=', 'finalized')->count();
            if ($open > 0) {
                abort(422, "Cannot lock period: {$open} statement(s) are not finalized.");
            }

            $period->status = 'locked';
            $period->locked_at = now();
            $period->locked_by = $this->actor()?->id;
            $period->save();

            $this->audit()->log('statements.period_lock', $period, [
                'start' => $period->start_date->toDateString(),
                'end' => $period->end_date->toDateString(),
            ]);

            return $period->fresh();
        });
    }

    public function ensureStatementAccess(OwnerStatement $statement): void
    {
        $this->ensureAgencyAccess($statement->agency_id);

        $actor = $this->actor();
        if (! $actor) return;

        // Owners see only their own statements.
        if ($actor->hasRole('owner')) {
            if ((int) $statement->owner_id !== (int) $actor->id) {
                abort(404);
            }
            return;
        }

        // Tenants and technicians have no statement access.
        if ($actor->hasRole('tenant') || $actor->hasRole('technician')) {
            abort(404);
        }
    }
}
