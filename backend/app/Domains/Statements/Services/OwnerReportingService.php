<?php

namespace App\Domains\Statements\Services;

use App\Domains\Property\Models\Property;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Services\DomainService;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementPeriod;

/**
 * OwnerReportingService — portfolio and profitability reports.
 * All numbers come from real queries. No fake KPIs.
 */
class OwnerReportingService extends DomainService
{
    /** Owner portfolio summary across all their statements. */
    public function portfolio(User $owner): array
    {
        $this->ensureOwnerAccess($owner);

        $propertyIds = app(StatementGenerationService::class)->ownerPropertyIds($owner);

        $properties = Property::whereIn('id', $propertyIds)
            ->withCount('units')
            ->get()
            ->map(function ($p) {
                $activeLeases = \App\Domains\Leasing\Models\Lease::where('property_id', $p->id)
                    ->where('status', 'active')->count();
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'units' => $p->units_count,
                    'active_leases' => $activeLeases,
                    'occupancy_rate' => $p->units_count > 0
                        ? round($activeLeases / $p->units_count * 100, 1)
                        : 0,
                ];
            })->all();

        $statements = OwnerStatement::where('agency_id', $owner->agency_id)
            ->where('owner_id', $owner->id)
            ->get();

        return [
            'owner' => ['id' => $owner->id, 'name' => $owner->name],
            'properties' => $properties,
            'property_count' => count($properties),
            'statements' => [
                'total' => $statements->count(),
                'finalized' => $statements->where('status', 'finalized')->count(),
                'pending' => $statements->whereIn('status', ['draft', 'review', 'approved'])->count(),
                'total_net' => round($statements->where('status', 'finalized')->sum('net_amount'), 2),
            ],
            'currency' => 'PKR',
        ];
    }

    /**
     * Property profitability (operational owner-level view).
     * Aggregates statement lines per property for a period.
     */
    public function profitability(User $owner, ?StatementPeriod $period = null): array
    {
        $this->ensureOwnerAccess($owner);

        $query = OwnerStatement::where('agency_id', $owner->agency_id)
            ->where('owner_id', $owner->id);

        if ($period) {
            $query->where('statement_period_id', $period->id);
        }

        $statementIds = $query->pluck('id')->all();

        $rows = \App\Domains\Statements\Models\StatementLine::whereIn('owner_statement_id', $statementIds)
            ->whereNotNull('property_id')
            ->selectRaw('property_id, line_type, SUM(amount) as total')
            ->groupBy('property_id', 'line_type')
            ->get();

        $byProperty = [];
        foreach ($rows as $r) {
            $pid = $r->property_id;
            if (! isset($byProperty[$pid])) {
                $byProperty[$pid] = [
                    'property_id' => $pid,
                    'gross_income' => 0, 'management_fee' => 0,
                    'owner_expenses' => 0, 'owner_maintenance' => 0,
                    'owner_utility_absorption' => 0, 'adjustments' => 0,
                ];
            }
            $val = round((float) $r->total, 2);
            switch ($r->line_type) {
                case 'income': $byProperty[$pid]['gross_income'] += $val; break;
                case 'management_fee': $byProperty[$pid]['management_fee'] += abs($val); break;
                case 'expense': $byProperty[$pid]['owner_expenses'] += abs($val); break;
                case 'maintenance': $byProperty[$pid]['owner_maintenance'] += abs($val); break;
                case 'utility': $byProperty[$pid]['owner_utility_absorption'] += abs($val); break;
                case 'adjustment': $byProperty[$pid]['adjustments'] += $val; break;
            }
        }

        $propertyNames = Property::whereIn('id', array_keys($byProperty))
            ->pluck('name', 'id')->all();

        $result = [];
        foreach ($byProperty as $pid => $data) {
            $data['property_name'] = $propertyNames[$pid] ?? "Property #{$pid}";
            $data['net'] = OwnerStatement::reconcile(
                $data['gross_income'], $data['management_fee'],
                $data['owner_expenses'], $data['owner_maintenance'],
                $data['owner_utility_absorption'], $data['adjustments']
            );
            // Operational view label.
            $data['view'] = 'operational owner-level (not audited accounting profit)';
            $result[] = $data;
        }

        return [
            'properties' => $result,
            'currency' => 'PKR',
            'note' => 'Operational owner-level profitability based on APRMS statement lines.',
        ];
    }

    /** Monthly trend of net amounts from finalized statements. */
    public function trend(User $owner, int $months = 12): array
    {
        $this->ensureOwnerAccess($owner);

        $rows = OwnerStatement::where('owner_statements.agency_id', $owner->agency_id)
            ->where('owner_statements.owner_id', $owner->id)
            ->where('owner_statements.status', 'finalized')
            ->join('statement_periods', 'statement_periods.id', '=', 'owner_statements.statement_period_id')
            ->selectRaw("strftime('%Y-%m', statement_periods.start_date) as month, SUM(owner_statements.net_amount) as net, SUM(owner_statements.gross_income) as income")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit($months)
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'net' => round((float) $r->net, 2),
                'income' => round((float) $r->income, 2),
            ])
            ->all();

        return ['trend' => array_reverse($rows), 'currency' => 'PKR'];
    }

    private function ensureOwnerAccess(User $owner): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('owner') && (int) $actor->id !== (int) $owner->id) {
            abort(404);
        }
        if ($actor->hasRole('tenant') || $actor->hasRole('technician')) {
            abort(404);
        }

        $this->ensureAgencyAccess($owner->agency_id);
    }
}
