<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\LateFee;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentAllocation;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * AllocationService — the APPROVED fixed waterfall.
 *
 * Order (deterministic, not user-configurable):
 *   1. Late fees (oldest accrued first)
 *   2. Utilities (oldest invoice first)
 *   3. Current rent (current-period invoice)
 *   4. Oldest arrears (older invoices, oldest first)
 *
 * Invariants:
 *   - sum(allocations) <= payment.amount
 *   - no allocation exceeds the charge's remaining balance
 *   - no negative balances created
 *   - excess stays as unallocated credit on the payment (never lost)
 */
class AllocationService extends DomainService
{
    /**
     * Allocate a payment across the tenant's outstanding charges.
     * Runs in a transaction with row locks. Idempotent per payment:
     * if allocations already exist, returns them without duplicating.
     */
    public function allocate(Payment $payment): array
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'posted') {
                abort(422, 'Only posted payments can be allocated.');
            }

            $existing = $payment->allocations()->get();
            if ($existing->isNotEmpty()) {
                return $existing->all(); // idempotent
            }

            $tenant = $payment->tenant;
            $remaining = round((float) $payment->amount, 2);
            $created = [];

            // 1. Late fees (oldest first).
            $lateFees = LateFee::where('tenant_id', $tenant->id)
                ->where('status', 'accrued')
                ->orderBy('accrued_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lateFees as $fee) {
                if ($remaining <= 0) break;
                $owed = $fee->outstanding();
                if ($owed <= 0) continue;
                $take = min($remaining, $owed);
                $created[] = $this->applyAllocation($payment, $fee, 'late_fee', $take);
                $remaining = round($remaining - $take, 2);
            }

            // 2-4. Invoices: utilities, then current rent, then arrears.
            $invoices = RentInvoice::where('tenant_id', $tenant->id)
                ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->whereRaw('total > paid_amount')
                ->orderBy('period_start')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentPeriod = now()->format('Y-m');

            // 2. Utilities (oldest invoice first).
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) break;
                $utilOwed = $this->utilitiesOutstanding($invoice);
                if ($utilOwed <= 0) continue;
                $take = min($remaining, $utilOwed);
                $created[] = $this->applyAllocation($payment, $invoice, 'utilities', $take);
                $remaining = round($remaining - $take, 2);
            }

            // 3. Current rent.
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) break;
                if ($invoice->period_start->format('Y-m') !== $currentPeriod) continue;
                $rentOwed = $this->rentOutstanding($invoice);
                if ($rentOwed <= 0) continue;
                $take = min($remaining, $rentOwed);
                $created[] = $this->applyAllocation($payment, $invoice, 'rent', $take);
                $remaining = round($remaining - $take, 2);
            }

            // 4. Oldest arrears.
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) break;
                if ($invoice->period_start->format('Y-m') === $currentPeriod) continue;
                $owed = $invoice->outstanding();
                if ($owed <= 0) continue;
                $take = min($remaining, $owed);
                $created[] = $this->applyAllocation($payment, $invoice, 'arrears', $take);
                $remaining = round($remaining - $take, 2);
            }

            // Invariant check.
            $totalAllocated = round(array_sum(array_map(fn ($a) => (float) $a->amount, $created)), 2);
            if ($totalAllocated > (float) $payment->amount + 0.001) {
                abort(500, 'Allocation invariant violated: allocated exceeds payment.');
            }

            $this->audit()->log('allocations.apply', $payment, [
                'payment_id' => $payment->id,
                'allocated' => $totalAllocated,
                'unallocated' => $remaining,
                'lines' => count($created),
            ]);

            return $created;
        });
    }

    /**
     * Reverse all allocations of a payment (for payment reversal).
     * Restores invoice paid_amounts.
     */
    public function reverseAllocations(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $allocations = $payment->allocations()->lockForUpdate()->get();

            foreach ($allocations as $alloc) {
                $charge = $alloc->allocatable;
                if ($charge instanceof RentInvoice) {
                    $charge->decrement('paid_amount', (float) $alloc->amount);
                    $this->refreshInvoiceStatus($charge->fresh());
                }
                // LateFee outstanding is derived from allocations; deleting suffices.
                $alloc->delete();
            }

            $this->audit()->log('allocations.reverse', $payment, [
                'payment_id' => $payment->id, 'lines' => $allocations->count(),
            ]);
        });
    }

    private function applyAllocation(Payment $payment, $charge, string $bucket, float $amount): PaymentAllocation
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            abort(500, 'Allocation amount must be positive.');
        }

        $alloc = PaymentAllocation::create([
            'agency_id' => $payment->agency_id,
            'payment_id' => $payment->id,
            'allocatable_type' => get_class($charge),
            'allocatable_id' => $charge->id,
            'amount' => $amount,
            'bucket' => $bucket,
        ]);

        // Bump the invoice's paid_amount (late fees are part of invoice total).
        if ($charge instanceof LateFee) {
            $invoice = $charge->invoice;
            $invoice->increment('paid_amount', $amount);
            $this->refreshInvoiceStatus($invoice->fresh());
            if ($charge->outstanding() <= 0) {
                $charge->update(['status' => 'paid']);
            }
        } elseif ($charge instanceof RentInvoice) {
            $charge->increment('paid_amount', $amount);
            $this->refreshInvoiceStatus($charge->fresh());
        }

        // Ledger: informational allocation entry (balance unchanged).
        app(TenantLedgerService::class)->post(
            $payment->tenant, 'allocation', 0, 0,
            "Allocated ₨".number_format($amount, 2)." from {$payment->receipt_number} to ".
            ($charge instanceof LateFee ? "late fee #{$charge->id}" : "invoice {$charge->invoice_number}")." ({$bucket})",
            [
                'lease_id' => $payment->lease_id,
                'reference_type' => PaymentAllocation::class,
                'reference_id' => $alloc->id,
                'entry_date' => $payment->payment_date->toDateString(),
            ]
        );

        return $alloc;
    }

    private function utilitiesOutstanding(RentInvoice $invoice): float
    {
        $paid = (float) $invoice->allocations()->where('bucket', 'utilities')->sum('amount');
        return max(0, round((float) $invoice->utilities - $paid, 2));
    }

    private function rentOutstanding(RentInvoice $invoice): float
    {
        // Rent portion = total - utilities - late_fee component already covered.
        $utilPaid = (float) $invoice->allocations()->where('bucket', 'utilities')->sum('amount');
        $rentPaid = max(0, (float) $invoice->paid_amount - $utilPaid);
        $rentTotal = (float) $invoice->base_rent + (float) $invoice->other_charges + (float) $invoice->late_fee;
        return max(0, round($rentTotal - $rentPaid, 2));
    }

    private function refreshInvoiceStatus(RentInvoice $invoice): void
    {
        if ($invoice->status === 'void') return;

        $outstanding = $invoice->outstanding();
        if ($outstanding <= 0) {
            $invoice->update(['status' => 'paid']);
        } elseif ((float) $invoice->paid_amount > 0) {
            $invoice->update(['status' => $invoice->isOverdue() ? 'overdue' : 'partially_paid']);
        } else {
            $invoice->update(['status' => $invoice->isOverdue() ? 'overdue' : 'issued']);
        }
    }
}
