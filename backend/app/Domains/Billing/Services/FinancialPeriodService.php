<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\FinancialPeriod;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * FinancialPeriodService — period locking foundation.
 *
 * Once a period is locked, ordinary writes to posted financial records
 * in that period are blocked (invoices, payments, late fees check this).
 * Corrections use adjustment/reversal entries, never mutations.
 */
class FinancialPeriodService extends DomainService
{
    public function list()
    {
        return FinancialPeriod::orderByDesc('period')
            ->paginate(24);
    }

    public function lock(string $period): FinancialPeriod
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            abort(422, 'Period must be YYYY-MM.');
        }

        return DB::transaction(function () use ($period) {
            $agencyId = $this->agencyId() ?? abort(422, 'Agency context required.');

            $record = FinancialPeriod::where('agency_id', $agencyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $record = FinancialPeriod::create([
                    'agency_id' => $agencyId, 'period' => $period, 'status' => 'open',
                ]);
                $record = FinancialPeriod::where('id', $record->id)->lockForUpdate()->first();
            }

            if ($record->isLocked()) {
                return $record; // idempotent
            }

            // Cannot lock the current or a future period.
            if ($period >= now()->format('Y-m')) {
                abort(422, 'Only past periods can be locked.');
            }

            $record->update([
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $this->actor()?->id,
            ]);

            $this->audit()->log('periods.lock', $record, ['period' => $period]);

            return $record;
        });
    }

    public function unlock(string $period): FinancialPeriod
    {
        return DB::transaction(function () use ($period) {
            $agencyId = $this->agencyId() ?? abort(422, 'Agency context required.');

            $record = FinancialPeriod::where('agency_id', $agencyId)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $record->isLocked()) {
                return $record;
            }

            $record->update(['status' => 'open', 'locked_at' => null, 'locked_by' => null]);

            $this->audit()->log('periods.unlock', $record, ['period' => $period]);

            return $record;
        });
    }

    public function isLocked(int $agencyId, string $period): bool
    {
        return FinancialPeriod::where('agency_id', $agencyId)
            ->where('period', $period)
            ->where('status', 'locked')
            ->exists();
    }
}
