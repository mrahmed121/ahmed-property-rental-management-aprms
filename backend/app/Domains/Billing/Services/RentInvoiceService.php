<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\FinancialPeriod;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * RentInvoiceService — creates and manages rent invoices.
 *
 * Idempotency: UNIQUE(agency_id, lease_id, period_start) guarantees one
 * invoice per lease per period, even under concurrent generation.
 */
class RentInvoiceService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = RentInvoice::with(['tenant:id,first_name,last_name', 'unit:id,unit_number', 'property:id,name'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        $this->applyScope($query);

        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['lease_id'])) $query->where('lease_id', $filters['lease_id']);
        if (! empty($filters['unit_id'])) $query->where('unit_id', $filters['unit_id']);
        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['overdue'])) $query->where('due_date', '<', now()->toDateString())->whereIn('status', ['issued', 'partially_paid']);
        if (! empty($filters['from'])) $query->where('issue_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->where('issue_date', '<=', $filters['to']);
        if (! empty($filters['min_amount'])) $query->where('total', '>=', $filters['min_amount']);
        if (! empty($filters['max_amount'])) $query->where('total', '<=', $filters['max_amount']);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'like', "%{$filters['search']}%")
                    ->orWhereHas('tenant', fn ($t) => $t->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): RentInvoice
    {
        $invoice = RentInvoice::with([
            'tenant', 'lease', 'unit', 'property', 'building',
            'lateFeeRecord', 'dunningReminders', 'createdBy:id,name',
        ])->findOrFail($id);
        $this->ensureInvoiceAccess($invoice);

        return $invoice;
    }

    /**
     * Create an invoice for a lease period. Validates the tenant/unit/lease
     * chain; rejects mismatched cross-links. Idempotent on (lease, period_start).
     */
    public function create(Lease $lease, array $data): RentInvoice
    {
        app(\App\Domains\Leasing\Services\LeaseService::class)->ensureLeaseAccess($lease);

        return DB::transaction(function () use ($lease, $data) {
            // Re-check inside the transaction for concurrency safety.
            $existing = RentInvoice::where('agency_id', $lease->agency_id)
                ->where('lease_id', $lease->id)
                ->whereDate('period_start', $data['period_start'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing; // idempotent: return the existing invoice
            }

            $this->assertPeriodOpen($lease->agency_id, $data['period_start']);

            $tenant = $lease->tenant;
            $unit = $lease->unit;

            // Cross-link integrity: tenant/unit/lease must belong together.
            if ($tenant->id !== (int) ($data['tenant_id'] ?? $tenant->id)) {
                abort(422, 'Tenant does not match the lease.');
            }

            $baseRent = round((float) $data['base_rent'], 2);
            $utilities = round((float) ($data['utilities'] ?? 0), 2);
            $other = round((float) ($data['other_charges'] ?? 0), 2);
            $total = round($baseRent + $utilities + $other, 2);

            if ($total <= 0) {
                abort(422, 'Invoice total must be positive.');
            }

            $invoice = RentInvoice::create([
                'agency_id' => $lease->agency_id,
                'tenant_id' => $tenant->id,
                'lease_id' => $lease->id,
                'unit_id' => $unit->id,
                'property_id' => $lease->property_id,
                'building_id' => $lease->building_id,
                'invoice_number' => $this->nextInvoiceNumber($lease->agency_id),
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'],
                'base_rent' => $baseRent,
                'utilities' => $utilities,
                'other_charges' => $other,
                'total' => $total,
                'status' => 'issued',
                'currency' => 'PKR',
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->actor()?->id,
            ]);

            // Ledger: debit the tenant.
            app(TenantLedgerService::class)->post(
                $tenant, 'invoice', $total, 0,
                "Rent invoice {$invoice->invoice_number} ({$invoice->period_start} → {$invoice->period_end})",
                [
                    'lease_id' => $lease->id,
                    'reference_type' => RentInvoice::class,
                    'reference_id' => $invoice->id,
                    'entry_date' => $invoice->issue_date->toDateString(),
                ]
            );

            $this->audit()->log('invoices.create', $invoice, [
                'invoice_number' => $invoice->invoice_number,
                'lease_id' => $lease->id, 'total' => $total,
            ]);

            return $invoice;
        });
    }

    /** Mark overdue invoices (due date passed, still unpaid). */
    public function refreshOverdue(): int
    {
        $count = 0;
        RentInvoice::whereIn('status', ['issued', 'partially_paid'])
            ->where('due_date', '<', now()->toDateString())
            ->whereRaw('total > paid_amount')
            ->chunkById(200, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    if ($invoice->status !== 'overdue') {
                        $invoice->update(['status' => 'overdue']);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Void an invoice (only if nothing allocated and period open). */
    public function void(RentInvoice $invoice): RentInvoice
    {
        $this->ensureInvoiceAccess($invoice);

        return DB::transaction(function () use ($invoice) {
            $invoice = RentInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'void') {
                abort(422, 'Invoice is already void.');
            }
            if ((float) $invoice->paid_amount > 0) {
                abort(422, 'Cannot void an invoice with payments. Reverse the payments first.');
            }
            $this->assertPeriodOpen($invoice->agency_id, $invoice->period_start);

            $invoice->update(['status' => 'void']);

            // Ledger: reverse the debit via a reversal entry.
            app(TenantLedgerService::class)->post(
                $invoice->tenant, 'reversal', 0, (float) $invoice->total,
                "Voided invoice {$invoice->invoice_number}",
                [
                    'lease_id' => $invoice->lease_id,
                    'reference_type' => RentInvoice::class,
                    'reference_id' => $invoice->id,
                ]
            );

            $this->audit()->log('invoices.void', $invoice, ['invoice_number' => $invoice->invoice_number]);

            return $invoice;
        });
    }

    public function ensureInvoiceAccess(RentInvoice $invoice): void
    {
        $this->ensureAgencyAccess($invoice->agency_id);

        $actor = $this->actor();
        if ($actor && $actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            if (! in_array($invoice->tenant_id, $tenantIds ?? [], true)) {
                abort(404);
            }
        }
        if ($actor && $actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds) && ! in_array($invoice->property_id, $propertyIds, true)) {
                abort(404);
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

        $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
        if (is_array($propertyIds)) {
            $query->whereIn('property_id', $propertyIds);
        }
    }

    private function assertPeriodOpen(int $agencyId, $date): void
    {
        $period = FinancialPeriod::periodFor($date instanceof \DateTimeInterface ? $date : new \DateTime($date));
        $locked = FinancialPeriod::where('agency_id', $agencyId)
            ->where('period', $period)
            ->where('status', 'locked')
            ->exists();

        if ($locked) {
            abort(422, "Financial period {$period} is locked. Use an adjustment instead.");
        }
    }

    private function nextInvoiceNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = RentInvoice::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->invoice_number, -6)) + 1 : 1;

        return sprintf('INV-%s-%06d', $year, $seq);
    }
}
