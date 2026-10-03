<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\FinancialPeriod;
use App\Domains\Billing\Models\Payment;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PaymentService — records tenant payments.
 *
 * - Idempotency: UNIQUE(agency_id, idempotency_key). Retried submissions
 *   with the same key return the existing payment.
 * - A payment is a manually recorded payment; no external confirmations
 *   are invented.
 * - Posting creates a ledger credit and runs the allocation waterfall.
 */
class PaymentService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Payment::with(['tenant:id,first_name,last_name', 'lease:id,lease_number'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $this->applyScope($query);

        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['lease_id'])) $query->where('lease_id', $filters['lease_id']);
        if (! empty($filters['method'])) $query->where('method', $filters['method']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['from'])) $query->where('payment_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->where('payment_date', '<=', $filters['to']);
        if (! empty($filters['min_amount'])) $query->where('amount', '>=', $filters['min_amount']);
        if (! empty($filters['max_amount'])) $query->where('amount', '<=', $filters['max_amount']);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('receipt_number', 'like', "%{$filters['search']}%")
                    ->orWhere('reference', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Payment
    {
        $payment = Payment::with([
            'tenant', 'lease', 'postedBy:id,name', 'allocations.allocatable',
        ])->findOrFail($id);
        $this->ensurePaymentAccess($payment);

        return $payment;
    }

    /**
     * Preview the waterfall allocation without posting.
     * Shows exactly where the money would go.
     */
    public function preview(Tenant $tenant, float $amount): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) abort(422, 'Amount must be positive.');

        $lines = [];
        $remaining = $amount;
        $currentPeriod = now()->format('Y-m');

        // 1. Late fees (oldest first).
        $lateFees = \App\Domains\Billing\Models\LateFee::where('tenant_id', $tenant->id)
            ->where('status', 'accrued')->orderBy('accrued_date')->orderBy('id')->get();
        foreach ($lateFees as $fee) {
            if ($remaining <= 0) break;
            $owed = $fee->outstanding();
            if ($owed <= 0) continue;
            $take = min($remaining, $owed);
            $lines[] = ['bucket' => 'late_fee', 'label' => "Late fee #{$fee->id} (invoice {$fee->invoice->invoice_number})", 'amount' => $take];
            $remaining = round($remaining - $take, 2);
        }

        // 2-4. Invoices.
        $invoices = \App\Domains\Billing\Models\RentInvoice::where('tenant_id', $tenant->id)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->whereRaw('total > paid_amount')
            ->orderBy('period_start')->orderBy('id')->get();

        // 2. Utilities.
        foreach ($invoices as $invoice) {
            if ($remaining <= 0) break;
            $utilPaid = (float) $invoice->allocations()->where('bucket', 'utilities')->sum('amount');
            $owed = max(0, round((float) $invoice->utilities - $utilPaid, 2));
            if ($owed <= 0) continue;
            $take = min($remaining, $owed);
            $lines[] = ['bucket' => 'utilities', 'label' => "Invoice {$invoice->invoice_number} (utilities)", 'amount' => $take];
            $remaining = round($remaining - $take, 2);
        }

        // 3. Current rent, 4. Oldest arrears.
        foreach (['rent', 'arrears'] as $bucket) {
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) break 2;
                $isCurrent = $invoice->period_start->format('Y-m') === $currentPeriod;
                if ($bucket === 'rent' && ! $isCurrent) continue;
                if ($bucket === 'arrears' && $isCurrent) continue;
                $owed = $invoice->outstanding();
                if ($owed <= 0) continue;
                $take = min($remaining, $owed);
                $lines[] = ['bucket' => $bucket, 'label' => "Invoice {$invoice->invoice_number}", 'amount' => $take];
                $remaining = round($remaining - $take, 2);
            }
        }

        return [
            'amount' => $amount,
            'lines' => $lines,
            'allocated' => round($amount - $remaining, 2),
            'unallocated' => $remaining,
        ];
    }

    /**
     * Record a payment. Idempotent via idempotency_key.
     */
    public function record(Tenant $tenant, array $data): Payment
    {
        app(\App\Domains\Leasing\Services\TenantService::class)->ensureTenantAccess($tenant);

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            abort(422, 'Payment amount must be positive.');
        }
        if (! in_array($data['method'], Payment::METHODS, true)) {
            abort(422, 'Invalid payment method.');
        }

        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        return DB::transaction(function () use ($tenant, $data, $amount, $idempotencyKey) {
            // Idempotency: return existing payment for this key.
            $existing = Payment::where('agency_id', $tenant->agency_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->assertPeriodOpen($tenant->agency_id, $data['payment_date']);

            $leaseId = $data['lease_id'] ?? null;
            if ($leaseId) {
                $lease = \App\Domains\Leasing\Models\Lease::findOrFail($leaseId);
                if ($lease->tenant_id !== $tenant->id || $lease->agency_id !== $tenant->agency_id) {
                    abort(422, 'Lease does not belong to this tenant.');
                }
            }

            $payment = Payment::create([
                'agency_id' => $tenant->agency_id,
                'tenant_id' => $tenant->id,
                'lease_id' => $leaseId,
                'receipt_number' => $this->nextReceiptNumber($tenant->agency_id),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'currency' => 'PKR',
                'posted_by' => $this->actor()?->id,
                'status' => 'posted',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Ledger: credit the tenant.
            app(TenantLedgerService::class)->post(
                $tenant, 'payment', 0, $amount,
                "Payment {$payment->receipt_number} ({$payment->method})",
                [
                    'lease_id' => $leaseId,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                    'entry_date' => $payment->payment_date->toDateString(),
                ]
            );

            // Run the waterfall.
            app(AllocationService::class)->allocate($payment->fresh());

            $this->audit()->log('payments.record', $payment, [
                'receipt_number' => $payment->receipt_number,
                'tenant_id' => $tenant->id, 'amount' => $amount,
                'method' => $payment->method,
            ]);

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Reverse a posted payment. Restores invoice paid_amounts via
     * allocation reversal and posts a reversing ledger entry.
     * The original payment row is kept (status=reversed) — never deleted.
     */
    public function reverse(Payment $payment, string $reason): Payment
    {
        $this->ensurePaymentAccess($payment);

        return DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'reversed') {
                abort(422, 'Payment is already reversed.');
            }
            $this->assertPeriodOpen($payment->agency_id, $payment->payment_date);

            app(AllocationService::class)->reverseAllocations($payment);

            $payment->update(['status' => 'reversed']);

            // Ledger: debit back the credited amount (reverses the payment effect).
            app(TenantLedgerService::class)->post(
                $payment->tenant, 'reversal', (float) $payment->amount, 0,
                "Reversed payment {$payment->receipt_number}: {$reason}",
                [
                    'lease_id' => $payment->lease_id,
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                ]
            );

            $this->audit()->log('payments.reverse', $payment, [
                'receipt_number' => $payment->receipt_number, 'reason' => $reason,
            ]);

            return $payment;
        });
    }

    public function ensurePaymentAccess(Payment $payment): void
    {
        $this->ensureAgencyAccess($payment->agency_id);

        $actor = $this->actor();
        if ($actor && $actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            if (! in_array($payment->tenant_id, $tenantIds ?? [], true)) {
                abort(404);
            }
        }
        if ($actor && $actor->hasRole('owner')) {
            $lease = $payment->lease;
            if ($lease) {
                $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
                if (is_array($propertyIds) && ! in_array($lease->property_id, $propertyIds, true)) {
                    abort(404);
                }
            }
        }
    }

    private function applyScope($query): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        $tenantIds = TenantAccess::accessibleTenantIds($actor);
        if (is_array($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereHas('lease', fn ($q) => $q->whereIn('property_id', $propertyIds));
            }
        }
    }

    private function assertPeriodOpen(int $agencyId, $date): void
    {
        $period = FinancialPeriod::periodFor($date instanceof \DateTimeInterface ? $date : new \DateTime($date));
        $locked = FinancialPeriod::where('agency_id', $agencyId)
            ->where('period', $period)->where('status', 'locked')->exists();

        if ($locked) {
            abort(422, "Financial period {$period} is locked. Use an adjustment instead.");
        }
    }

    private function nextReceiptNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = Payment::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('receipt_number', 'like', "RCPT-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->receipt_number, -6)) + 1 : 1;

        return sprintf('RCPT-%s-%06d', $year, $seq);
    }
}
