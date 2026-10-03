<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Shared\Services\DomainService;

/**
 * FinancialDashboardService — REAL query-backed money metrics.
 * Every number comes from the database. No frontend guesses.
 */
class FinancialDashboardService extends DomainService
{
    public function metrics(): array
    {
        $actor = $this->actor();
        $tenantIds = $actor ? TenantAccess::accessibleTenantIds($actor) : null;
        $propertyIds = null;
        if ($actor && $actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
        }

        $invoiceQuery = RentInvoice::query();
        $paymentQuery = Payment::where('status', 'posted');

        if (is_array($tenantIds)) {
            $invoiceQuery->whereIn('tenant_id', $tenantIds);
            $paymentQuery->whereIn('tenant_id', $tenantIds);
        } elseif (is_array($propertyIds)) {
            $invoiceQuery->whereIn('property_id', $propertyIds);
            $paymentQuery->whereHas('lease', fn ($q) => $q->whereIn('property_id', $propertyIds));
        }

        $period = now()->format('Y-m');
        $periodStart = $period.'-01';

        // Billed this period (non-void invoices issued in period).
        $billedThisPeriod = (clone $invoiceQuery)
            ->where('issue_date', '>=', $periodStart)
            ->where('status', '!=', 'void')
            ->sum('total');

        // Collected this period (posted payments in period).
        $collectedThisPeriod = (clone $paymentQuery)
            ->where('payment_date', '>=', $periodStart)
            ->sum('amount');

        // Outstanding (unpaid on non-void invoices).
        $outstanding = (clone $invoiceQuery)
            ->where('status', '!=', 'void')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as v')
            ->value('v');

        // Overdue.
        $overdue = (clone $invoiceQuery)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as v')
            ->value('v');

        // Collection rate this period.
        $collectionRate = $billedThisPeriod > 0
            ? round(min(100, $collectedThisPeriod / $billedThisPeriod * 100), 1)
            : null;

        // Invoices due (unpaid, due date >= today) and overdue counts.
        $invoicesDue = (clone $invoiceQuery)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->where('due_date', '>=', now()->toDateString())
            ->count();
        $invoicesOverdue = (clone $invoiceQuery)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->count();

        // Payments today.
        $paymentsToday = (clone $paymentQuery)
            ->where('payment_date', now()->toDateString())
            ->sum('amount');

        // Arrears aging buckets (based on due date, unpaid portion).
        $aging = $this->aging($invoiceQuery);

        return [
            'billed_this_period' => round((float) $billedThisPeriod, 2),
            'collected_this_period' => round((float) $collectedThisPeriod, 2),
            'outstanding' => round((float) $outstanding, 2),
            'overdue' => round((float) $overdue, 2),
            'collection_rate' => $collectionRate,
            'invoices_due' => $invoicesDue,
            'invoices_overdue' => $invoicesOverdue,
            'payments_today' => round((float) $paymentsToday, 2),
            'arrears_aging' => $aging,
            'currency' => 'PKR',
            'period' => $period,
        ];
    }

    /** Arrears grouped by days overdue. No double-counting (uses paid_amount). */
    public function aging($baseQuery): array
    {
        $buckets = [
            '0_30' => [0, 30], '31_60' => [31, 60],
            '61_90' => [61, 90], '90_plus' => [91, 99999],
        ];
        $result = [];

        foreach ($buckets as $key => [$from, $to]) {
            $q = clone $baseQuery;
            $cutoffFrom = now()->subDays($to)->toDateString();
            $cutoffTo = now()->subDays($from)->toDateString();

            $value = $q->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->where('due_date', '<', now()->toDateString())
                ->where('due_date', '>', $cutoffFrom)
                ->where('due_date', '<=', $cutoffTo)
                ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as v')
                ->value('v');

            $result[$key] = round((float) $value, 2);
        }

        return $result;
    }

    /** Arrears list: tenants with outstanding, oldest first. */
    public function arrears(array $filters = [])
    {
        $actor = $this->actor();
        $tenantIds = $actor ? TenantAccess::accessibleTenantIds($actor) : null;

        $query = RentInvoice::with(['tenant:id,first_name,last_name', 'unit:id,unit_number', 'property:id,name'])
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->whereRaw('total > paid_amount')
            ->orderBy('due_date');

        if (is_array($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
        }

        if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
