<?php

namespace App\Domains\Maintenance\Services;

use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Shared\Services\DomainService;

/**
 * MaintenanceDashboardService — REAL query-backed maintenance metrics.
 */
class MaintenanceDashboardService extends DomainService
{
    public function metrics(): array
    {
        $actor = $this->actor();
        $base = MaintenanceTicket::query();
        $this->applyScope($base, $actor);

        $open = (clone $base)->whereNotIn('status', ['closed', 'cancelled'])->count();
        $urgent = (clone $base)->where('priority', 'urgent')
            ->whereNotIn('status', ['closed', 'cancelled'])->count();
        $breached = (clone $base)->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['closed', 'cancelled', 'verified'])->count();
        $dueSoon = (clone $base)->where('sla_due_at', '>=', now())
            ->where('sla_due_at', '<', now()->addHours(24))
            ->whereNotIn('status', ['closed', 'cancelled', 'verified'])->count();
        $pendingApprovals = (clone $base)->where('status', 'approval_pending')->count();
        $inProgress = (clone $base)->where('status', 'in_progress')->count();
        $assigned = (clone $base)->where('status', 'assigned')->count();
        $completedThisMonth = (clone $base)->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfMonth())->count();

        // Maintenance spend: sum of approved quote totals (actual records only).
        $spend = (clone $base)
            ->join('maintenance_quotes', 'maintenance_quotes.ticket_id', '=', 'maintenance_tickets.id')
            ->where('maintenance_quotes.status', 'approved')
            ->sum('maintenance_quotes.total');

        $byPriority = [];
        foreach (MaintenanceTicket::PRIORITIES as $p) {
            $byPriority[$p] = (clone $base)->where('priority', $p)
                ->whereNotIn('status', ['closed', 'cancelled'])->count();
        }

        $byStatus = [];
        foreach (['open', 'triaged', 'assigned', 'quoted', 'approval_pending', 'approved', 'in_progress', 'completed', 'verified'] as $s) {
            $byStatus[$s] = (clone $base)->where('status', $s)->count();
        }

        return [
            'open_tickets' => $open,
            'urgent_tickets' => $urgent,
            'sla_breached' => $breached,
            'sla_due_soon' => $dueSoon,
            'pending_approvals' => $pendingApprovals,
            'in_progress' => $inProgress,
            'assigned' => $assigned,
            'completed_this_month' => $completedThisMonth,
            'approved_spend' => round((float) $spend, 2),
            'currency' => 'PKR',
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
        ];
    }

    private function applyScope($query, $actor): void
    {
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            $query->where('assigned_to', $actor->id);
            return;
        }

        $tenantIds = TenantAccess::accessibleTenantIds($actor);
        if (is_array($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }
        }
    }
}
