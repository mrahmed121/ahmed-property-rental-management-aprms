<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Payment;
use App\Domains\Shared\Services\DomainService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * ReceiptService — builds receipt data and PDFs for posted payments.
 *
 * Receipt contains: tenant, property, unit, lease, payment date, amount,
 * method, reference, allocation summary, remaining balance, agency info.
 * Deterministic numbering comes from the payment's receipt_number (RCPT-...).
 * No email claim: we generate the document; delivery is out of scope.
 */
class ReceiptService extends DomainService
{
    /** Structured receipt data for a payment. */
    public function data(Payment $payment): array
    {
        app(PaymentService::class)->ensurePaymentAccess($payment);

        $payment->loadMissing([
            'tenant', 'lease.unit', 'lease.property', 'allocations.allocatable', 'postedBy:id,name',
        ]);

        $tenant = $payment->tenant;
        $lease = $payment->lease;
        $agency = $tenant->agency;

        $allocations = $payment->allocations->map(function ($a) {
            $target = $a->allocatable;
            return [
                'bucket' => $a->bucket,
                'target' => $target instanceof \App\Domains\Billing\Models\LateFee
                    ? 'Late fee #'.$target->id
                    : 'Invoice '.$target->invoice_number,
                'amount' => (float) $a->amount,
            ];
        })->all();

        $balance = app(TenantLedgerService::class)->balance($tenant);

        $data = [
            'receipt_number' => $payment->receipt_number,
            'payment_date' => $payment->payment_date->toDateString(),
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'posted_by' => $payment->postedBy?->name,
            'agency' => [
                'name' => $agency->name ?? 'APRMS Agency',
            ],
            'tenant' => [
                'name' => $tenant->first_name.' '.$tenant->last_name,
                'email' => $tenant->email, 'phone' => $tenant->phone,
            ],
            'lease' => $lease ? [
                'lease_number' => $lease->lease_number,
                'unit' => $lease->unit?->unit_number,
                'property' => $lease->property?->name,
            ] : null,
            'allocations' => $allocations,
            'allocated_total' => round(array_sum(array_column($allocations, 'amount')), 2),
            'unallocated' => $payment->unallocated(),
            'tenant_balance' => $balance,
        ];

        $this->audit()->log('receipts.view', $payment, [
            'receipt_number' => $payment->receipt_number,
        ]);

        return $data;
    }

    /** Render the receipt as a PDF download. */
    public function pdf(Payment $payment)
    {
        $data = $this->data($payment);

        $html = view('billing.receipt', $data)->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download(
            $payment->receipt_number.'.pdf'
        );
    }
}
