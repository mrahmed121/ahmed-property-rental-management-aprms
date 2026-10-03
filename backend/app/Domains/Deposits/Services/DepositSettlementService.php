<?php

namespace App\Domains\Deposits\Services;

use App\Domains\Billing\Services\TenantLedgerService;
use App\Domains\Deposits\Models\Deposit;
use App\Domains\Deposits\Models\DepositDeduction;
use App\Domains\Deposits\Models\DepositSettlement;
use App\Domains\Leasing\Models\MoveOutInspection;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * DepositSettlementService — final deposit settlement.
 *
 * Flow: inspection (required) → deductions (proposed → approved) →
 * settlement draft → preview → finalize (locked).
 *
 * Settlement math: refund = gross − approved deductions − applied_to_balance.
 * - Deductions need an approved, auditable reason (no arbitrary deductions).
 * - Applied-to-balance flows through the P4 tenant ledger.
 * - Finalization is locked; corrections use reversal, not edits.
 * - Concurrency: row lock on the deposit; UNIQUE(agency_id, deposit_id)
 *   prevents double settlement.
 */
class DepositSettlementService extends DomainService
{
    /**
     * Propose a deduction against the deposit. Requires a reason and,
     * for damage, should reference the move-out inspection.
     */
    public function proposeDeduction(Deposit $deposit, array $data): DepositDeduction
    {
        app(DepositService::class)->ensureDepositAccess($deposit);

        return DB::transaction(function () use ($deposit, $data) {
            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->isSettled()) {
                abort(422, 'Cannot add deductions to a settled deposit.');
            }

            if (! in_array($data['category'], DepositDeduction::CATEGORIES, true)) {
                abort(422, 'Invalid deduction category.');
            }
            if (! in_array($data['assessment'] ?? 'damage', DepositDeduction::ASSESSMENTS, true)) {
                abort(422, 'Assessment must be wear or damage.');
            }
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                abort(422, 'Deduction amount must be positive.');
            }
            if (trim($data['description'] ?? '') === '') {
                abort(422, 'A deduction description/reason is required.');
            }

            // Deductions cannot exceed the held amount (considering proposed ones).
            $proposed = (float) $deposit->deductions()
                ->whereIn('status', ['proposed', 'approved'])->sum('amount');
            if (round($proposed + $amount, 2) > (float) $deposit->held_amount + 0.01) {
                abort(422, 'Deductions cannot exceed the held deposit.');
            }

            $deduction = DepositDeduction::create([
                'agency_id' => $deposit->agency_id,
                'deposit_id' => $deposit->id,
                'inspection_id' => $data['inspection_id'] ?? null,
                'category' => $data['category'],
                'assessment' => $data['assessment'] ?? 'damage',
                'description' => $data['description'],
                'amount' => $amount,
                'status' => 'proposed',
                'created_by' => $this->actor()?->id,
            ]);

            $this->audit()->log('deposits.deduction_propose', $deduction, [
                'deposit_id' => $deposit->id, 'amount' => $amount,
            ]);

            return $deduction;
        });
    }

    /** Approve or reject a proposed deduction. */
    public function reviewDeduction(DepositDeduction $deduction, string $decision): DepositDeduction
    {
        app(DepositService::class)->ensureDepositAccess($deduction->deposit);

        return DB::transaction(function () use ($deduction, $decision) {
            $deduction = DepositDeduction::where('id', $deduction->id)->lockForUpdate()->firstOrFail();

            if ($deduction->status !== 'proposed') {
                abort(422, 'Only proposed deductions can be reviewed.');
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                abort(422, 'Decision must be approved or rejected.');
            }

            $deduction->update([
                'status' => $decision,
                'reviewed_by' => $this->actor()?->id,
                'reviewed_at' => now(),
            ]);

            $this->audit()->log('deposits.deduction_review', $deduction, [
                'decision' => $decision,
            ]);

            return $deduction;
        });
    }

    /**
     * Build (or return) the draft settlement for a deposit.
     * Requires a reviewed move-out inspection.
     */
    public function draft(Deposit $deposit, int $inspectionId): DepositSettlement
    {
        app(DepositService::class)->ensureDepositAccess($deposit);

        return DB::transaction(function () use ($deposit, $inspectionId) {
            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->isSettled()) {
                abort(422, 'Deposit is already settled.');
            }
            if ((float) $deposit->held_amount <= 0) {
                abort(422, 'No held deposit to settle.');
            }

            $inspection = MoveOutInspection::where('id', $inspectionId)->firstOrFail();
            if ($inspection->agency_id !== $deposit->agency_id
                || $inspection->lease_id !== $deposit->lease_id) {
                abort(422, 'Inspection does not belong to this deposit/lease.');
            }
            if ($inspection->review_status !== 'reviewed') {
                abort(422, 'Final settlement requires a reviewed move-out inspection.');
            }

            $existing = DepositSettlement::where('deposit_id', $deposit->id)->first();
            if ($existing) {
                return $existing; // idempotent draft
            }

            $settlement = DepositSettlement::create([
                'agency_id' => $deposit->agency_id,
                'deposit_id' => $deposit->id,
                'tenant_id' => $deposit->tenant_id,
                'lease_id' => $deposit->lease_id,
                'inspection_id' => $inspection->id,
                'gross_deposit' => $deposit->held_amount,
                'status' => 'draft',
            ]);

            // Link approved deductions to this settlement.
            $deposit->deductions()->where('status', 'approved')
                ->update(['settlement_id' => $settlement->id]);

            $this->recalculate($settlement->fresh());

            $this->audit()->log('deposits.settlement_draft', $settlement, [
                'deposit_id' => $deposit->id, 'inspection_id' => $inspection->id,
            ]);

            return $settlement->fresh();
        });
    }

    /**
     * Preview the settlement: gross, approved deductions, applied amount,
     * refund. Deterministic; no writes.
     */
    public function preview(DepositSettlement $settlement, float $applyToBalance = 0): array
    {
        app(DepositService::class)->ensureDepositAccess($settlement->deposit);

        $deductions = (float) $settlement->deductions()->where('status', 'approved')->sum('amount');
        $calc = DepositSettlement::calculate(
            (float) $settlement->gross_deposit, $deductions, $applyToBalance
        );

        // Cap the applied amount at the tenant's actual outstanding balance.
        $outstanding = max(0, app(TenantLedgerService::class)->balance($settlement->tenant));

        return $calc + [
            'settlement_id' => $settlement->id,
            'tenant_outstanding' => $outstanding,
            'deduction_lines' => $settlement->deductions()->where('status', 'approved')->get()
                ->map(fn ($d) => [
                    'id' => $d->id, 'category' => $d->category,
                    'assessment' => $d->assessment,
                    'description' => $d->description,
                    'amount' => (float) $d->amount,
                ])->all(),
        ];
    }

    /**
     * Finalize the settlement. Locked afterwards.
     * - Deductions → deposit transactions (type=deduction).
     * - Applied amount → P4 ledger (reduces tenant outstanding).
     * - Refund → deposit transaction (type=refund) + internal refund state.
     */
    public function finalize(DepositSettlement $settlement, array $data): DepositSettlement
    {
        app(DepositService::class)->ensureDepositAccess($settlement->deposit);

        return DB::transaction(function () use ($settlement, $data) {
            $settlement = DepositSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            if ($settlement->isFinalized()) {
                return $settlement; // idempotent
            }

            $deposit = Deposit::where('id', $settlement->deposit_id)->lockForUpdate()->firstOrFail();

            $applyToBalance = round((float) ($data['apply_to_balance'] ?? 0), 2);
            $deductions = (float) $settlement->deductions()->where('status', 'approved')->sum('amount');

            // Applied amount cannot exceed the tenant's actual outstanding
            // (the positive amount owed; credits don't count).
            $outstanding = max(0, app(TenantLedgerService::class)->balance($deposit->tenant));
            if ($applyToBalance > $outstanding + 0.01) {
                abort(422, 'Cannot apply more than the tenant\'s outstanding balance (₨'.number_format($outstanding, 2).').');
            }

            $calc = DepositSettlement::calculate(
                (float) $settlement->gross_deposit, $deductions, $applyToBalance
            );

            $depositSvc = app(DepositService::class);
            $remaining = (float) $deposit->held_amount;

            // 1. Deduction transactions.
            foreach ($settlement->deductions()->where('status', 'approved')->get() as $d) {
                $remaining = round($remaining - (float) $d->amount, 2);
                $depositSvc->recordTransaction(
                    $deposit, 'deduction', (float) $d->amount, $remaining,
                    "Approved deduction: {$d->description} ({$d->assessment})",
                    ['reference_type' => DepositDeduction::class, 'reference_id' => $d->id]
                );
            }

            // 2. Applied to outstanding → P4 ledger credit.
            if ($applyToBalance > 0) {
                $remaining = round($remaining - $applyToBalance, 2);
                $depositSvc->recordTransaction(
                    $deposit, 'applied', $applyToBalance, $remaining,
                    'Deposit applied to outstanding tenant balance.',
                    ['reference_type' => DepositSettlement::class, 'reference_id' => $settlement->id]
                );

                app(TenantLedgerService::class)->post(
                    $deposit->tenant, 'payment', 0, $applyToBalance,
                    "Deposit applied to balance (settlement #{$settlement->id})",
                    [
                        'lease_id' => $deposit->lease_id,
                        'reference_type' => DepositSettlement::class,
                        'reference_id' => $settlement->id,
                    ]
                );
            }

            // 3. Refund → transaction + internal refund state.
            $refund = $calc['refund_amount'];
            if ($refund > 0) {
                $remaining = round($remaining - $refund, 2);
                $depositSvc->recordTransaction(
                    $deposit, 'refund', $refund, $remaining,
                    'Deposit refund due to tenant (internal settlement state; no external payment processed).',
                    ['reference_type' => DepositSettlement::class, 'reference_id' => $settlement->id]
                );
            }

            $settlement->update([
                'total_deductions' => $calc['total_deductions'],
                'applied_to_balance' => $calc['applied_to_balance'],
                'refund_amount' => $calc['refund_amount'],
                'status' => 'finalized',
                'finalized_at' => now(),
                'approved_by' => $this->actor()?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $deposit->update([
                'held_amount' => max(0, $remaining),
                'status' => 'settled',
                'release_date' => now()->toDateString(),
            ]);

            $this->audit()->log('deposits.settlement_finalize', $settlement, $calc + [
                'deposit_id' => $deposit->id,
            ]);

            return $settlement->fresh();
        });
    }

    /**
     * Reverse a finalized settlement (correction path).
     * Restores the held amount; the settlement returns to draft.
     */
    public function reverse(DepositSettlement $settlement, string $reason): DepositSettlement
    {
        app(DepositService::class)->ensureDepositAccess($settlement->deposit);

        return DB::transaction(function () use ($settlement, $reason) {
            $settlement = DepositSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            if (! $settlement->isFinalized()) {
                abort(422, 'Only finalized settlements can be reversed.');
            }
            if (trim($reason) === '') {
                abort(422, 'A reason is required.');
            }

            $deposit = Deposit::where('id', $settlement->deposit_id)->lockForUpdate()->firstOrFail();

            // Restore held amount to gross; post a reversing transaction.
            $restored = round((float) $deposit->held_amount + (float) $settlement->total_deductions
                + (float) $settlement->applied_to_balance + (float) $settlement->refund_amount, 2);

            app(DepositService::class)->recordTransaction(
                $deposit, 'adjustment', $restored - (float) $deposit->held_amount, $restored,
                "Settlement #{$settlement->id} reversed: {$reason}",
                ['reference_type' => DepositSettlement::class, 'reference_id' => $settlement->id]
            );

            // Reverse the ledger credit for the applied amount.
            if ((float) $settlement->applied_to_balance > 0) {
                app(TenantLedgerService::class)->post(
                    $deposit->tenant, 'reversal', (float) $settlement->applied_to_balance, 0,
                    "Reversed deposit application (settlement #{$settlement->id})",
                    [
                        'lease_id' => $deposit->lease_id,
                        'reference_type' => DepositSettlement::class,
                        'reference_id' => $settlement->id,
                    ]
                );
            }

            $settlement->update(['status' => 'draft', 'finalized_at' => null]);
            $deposit->update(['held_amount' => $restored, 'status' => 'held', 'release_date' => null]);

            $this->audit()->log('deposits.settlement_reverse', $settlement, ['reason' => $reason]);

            return $settlement->fresh();
        });
    }

    private function recalculate(DepositSettlement $settlement): void
    {
        $deductions = (float) $settlement->deductions()->where('status', 'approved')->sum('amount');
        $calc = DepositSettlement::calculate((float) $settlement->gross_deposit, $deductions, 0);
        $settlement->update([
            'total_deductions' => $calc['total_deductions'],
            'refund_amount' => $calc['refund_amount'],
        ]);
    }
}
