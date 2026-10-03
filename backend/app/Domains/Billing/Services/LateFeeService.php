<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\LateFee;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Shared\Models\Setting;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * LateFeeService — accrues late fees from agency settings.
 *
 * Rules (from settings, never invented):
 *   - billing.late_fee_type: "percent" | "flat"
 *   - billing.late_fee_value: 5 (percent) or flat amount
 *   - billing.late_fee_cap: max fee per invoice
 *   - billing.grace_days: days after due date before fee accrues
 *
 * Idempotent: UNIQUE(agency_id, invoice_id) — one fee per invoice.
 */
class LateFeeService extends DomainService
{
    /**
     * Accrue late fees for overdue invoices past their grace period.
     * Returns the fees created.
     */
    public function accrueOverdue(?int $agencyId = null): array
    {
        $agencyId ??= $this->agencyId();
        $created = [];

        $query = RentInvoice::with('tenant')
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->whereRaw('total > paid_amount')
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId));

        $query->chunkById(200, function ($invoices) use (&$created) {
            foreach ($invoices as $invoice) {
                $fee = $this->accrueForInvoice($invoice);
                if ($fee) $created[] = $fee;
            }
        });

        if (! empty($created)) {
            $this->audit()->log('late_fees.accrue', null, ['count' => count($created)]);
        }

        return $created;
    }

    /** Accrue for a single invoice if due. Idempotent. */
    public function accrueForInvoice(RentInvoice $invoice): ?LateFee
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = RentInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Already has a fee → idempotent no-op.
            $existing = LateFee::where('agency_id', $invoice->agency_id)
                ->where('invoice_id', $invoice->id)->first();
            if ($existing) return null;

            if (! in_array($invoice->status, ['issued', 'partially_paid', 'overdue'], true)) {
                return null;
            }
            if ($invoice->outstanding() <= 0) return null;

            $graceDays = (int) $this->setting($invoice->agency_id, 'grace_days', 3);
            $graceEnd = $invoice->due_date->copy()->addDays($graceDays);
            if (! now()->greaterThan($graceEnd)) return null; // still in grace

            $type = $this->setting($invoice->agency_id, 'late_fee_type', 'percent');
            $value = (float) $this->setting($invoice->agency_id, 'late_fee_value', 5);
            $cap = (float) $this->setting($invoice->agency_id, 'late_fee_cap', 5000);

            $amount = $type === 'flat'
                ? $value
                : round($invoice->outstanding() * $value / 100, 2, PHP_ROUND_HALF_UP);
            $amount = min($amount, $cap);
            $amount = round($amount, 2);

            if ($amount <= 0) return null;

            $fee = LateFee::create([
                'agency_id' => $invoice->agency_id,
                'tenant_id' => $invoice->tenant_id,
                'lease_id' => $invoice->lease_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'status' => 'accrued',
                'accrued_date' => now()->toDateString(),
                'rule_snapshot' => [
                    'late_fee_type' => $type, 'late_fee_value' => $value,
                    'late_fee_cap' => $cap, 'grace_days' => $graceDays,
                ],
            ]);

            // Add to the invoice total so the amount owed is visible in one place.
            $invoice->increment('total', $amount);
            $invoice->increment('late_fee', $amount);
            if ($invoice->status !== 'overdue') {
                $invoice->update(['status' => 'overdue']);
            }

            // Ledger: debit the tenant.
            app(TenantLedgerService::class)->post(
                $invoice->tenant, 'late_fee', $amount, 0,
                "Late fee for invoice {$invoice->invoice_number}",
                [
                    'lease_id' => $invoice->lease_id,
                    'reference_type' => LateFee::class,
                    'reference_id' => $fee->id,
                ]
            );

            $this->audit()->log('late_fees.accrue', $fee, [
                'invoice_id' => $invoice->id, 'amount' => $amount,
            ]);

            return $fee;
        });
    }

    /** Waive a late fee (manager action, audited). */
    public function waive(LateFee $fee): LateFee
    {
        $this->ensureAgencyAccess($fee->agency_id);

        return DB::transaction(function () use ($fee) {
            $fee = LateFee::where('id', $fee->id)->lockForUpdate()->firstOrFail();
            if ($fee->status !== 'accrued') {
                abort(422, 'Only accrued fees can be waived.');
            }

            $fee->update(['status' => 'waived']);

            $invoice = $fee->invoice;
            $invoice->decrement('total', (float) $fee->amount);
            $invoice->decrement('late_fee', (float) $fee->amount);

            app(TenantLedgerService::class)->post(
                $fee->tenant, 'adjustment', 0, (float) $fee->amount,
                "Waived late fee #{$fee->id} (invoice {$invoice->invoice_number})",
                [
                    'lease_id' => $fee->lease_id,
                    'reference_type' => LateFee::class,
                    'reference_id' => $fee->id,
                ]
            );

            $this->audit()->log('late_fees.waive', $fee, ['amount' => (float) $fee->amount]);

            return $fee;
        });
    }

    private function setting(int $agencyId, string $key, mixed $default): mixed
    {
        $s = Setting::where('agency_id', $agencyId)->where('key', $key)->first();
        return $s ? $s->typedValue() : $default;
    }
}
