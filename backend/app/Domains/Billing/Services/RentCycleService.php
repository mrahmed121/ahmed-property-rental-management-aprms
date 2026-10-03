<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Shared\Services\DomainService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RentCycleService — monthly rent invoice generation.
 *
 * PRORATION RULE (documented, deterministic):
 *   prorated_rent = monthly_rent × (billable_days / days_in_month),
 *   rounded half-up to 2 decimals (PKR paisa).
 *   billable_days = days in [period_start, period_end] ∩ [lease_start, lease_end].
 *   A full month bills the full monthly_rent (no rounding drift).
 *
 * IDEMPOTENCY: UNIQUE(agency_id, lease_id, period_start). Concurrent runs
 * for the same lease+period return the existing invoice instead of duplicating.
 */
class RentCycleService extends DomainService
{
    /**
     * Generate invoices for all eligible active leases for a period.
     * $period: "YYYY-MM". $dryRun: report without writing.
     */
    public function generateForPeriod(string $period, bool $dryRun = false): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            abort(422, 'Period must be YYYY-MM.');
        }

        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $agencyId = $this->agencyId();

        // Eligible: active leases overlapping the period, unit not archived.
        $leases = Lease::with(['tenant', 'unit'])
            ->where('status', 'active')
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->where('end_date', '>=', $start->toDateString())
                    ->orWhereNull('end_date');
            })
            ->whereHas('unit', fn ($q) => $q->whereNull('deleted_at'))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->get();

        $wouldCreate = [];
        $created = [];
        $skipped = [];

        foreach ($leases as $lease) {
            $existing = RentInvoice::where('agency_id', $lease->agency_id)
                ->where('lease_id', $lease->id)
                ->whereDate('period_start', $start->toDateString())
                ->first();

            if ($existing) {
                $skipped[] = ['lease_id' => $lease->id, 'reason' => 'invoice exists: '.$existing->invoice_number];
                continue;
            }

            $billableDays = $this->billableDays($lease, $start, $end);
            if ($billableDays <= 0) {
                $skipped[] = ['lease_id' => $lease->id, 'reason' => 'no billable days in period'];
                continue;
            }

            $baseRent = $this->prorate((float) $lease->monthly_rent, $billableDays, $start->daysInMonth);
            $dueDay = (int) $this->setting($lease->agency_id, 'rent_due_day', 1);
            $dueDate = $start->copy()->day(min($dueDay, $start->daysInMonth));

            $plan = [
                'lease_id' => $lease->id,
                'tenant' => $lease->tenant->first_name.' '.$lease->tenant->last_name,
                'unit' => $lease->unit->unit_number,
                'period' => $period,
                'billable_days' => $billableDays,
                'days_in_month' => $start->daysInMonth,
                'base_rent' => $baseRent,
                'due_date' => $dueDate->toDateString(),
            ];

            if ($dryRun) {
                $wouldCreate[] = $plan;
                continue;
            }

            $invoice = app(RentInvoiceService::class)->create($lease, [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'base_rent' => $baseRent,
                'notes' => $billableDays < $start->daysInMonth
                    ? "Prorated: {$billableDays}/{$start->daysInMonth} days."
                    : null,
            ]);

            $created[] = $plan + ['invoice_number' => $invoice->invoice_number];
        }

        if (! $dryRun) {
            $this->audit()->log('rent_cycle.generate', null, [
                'period' => $period, 'created' => count($created),
                'skipped' => count($skipped),
            ]);
        }

        return [
            'period' => $period,
            'dry_run' => $dryRun,
            'created' => $created,
            'would_create' => $wouldCreate,
            'skipped' => $skipped,
        ];
    }

    /** Days of the lease falling inside the period (inclusive). */
    public function billableDays(Lease $lease, Carbon $periodStart, Carbon $periodEnd): int
    {
        $leaseStart = Carbon::parse($lease->start_date)->startOfDay();
        $leaseEnd = Carbon::parse($lease->end_date)->startOfDay();

        $from = $leaseStart->greaterThan($periodStart) ? $leaseStart : $periodStart->copy()->startOfDay();
        $to = $leaseEnd->lessThan($periodEnd) ? $leaseEnd : $periodEnd->copy()->startOfDay();

        if ($from->greaterThan($to)) return 0;

        return $from->diffInDays($to) + 1;
    }

    /** Prorate monthly rent. Full month → exact monthly_rent (no drift). */
    public function prorate(float $monthlyRent, int $billableDays, int $daysInMonth): float
    {
        if ($billableDays >= $daysInMonth) {
            return round($monthlyRent, 2);
        }

        return round($monthlyRent * $billableDays / $daysInMonth, 2, PHP_ROUND_HALF_UP);
    }

    private function setting(int $agencyId, string $key, mixed $default): mixed
    {
        $s = \App\Domains\Shared\Models\Setting::where('agency_id', $agencyId)
            ->where('key', $key)->first();

        return $s ? $s->typedValue() : $default;
    }
}
