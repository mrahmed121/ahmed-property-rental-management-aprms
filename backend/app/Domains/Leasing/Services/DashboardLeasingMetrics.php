<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Models\User;

/**
 * DashboardLeasingMetrics — P3 numbers for the dashboard.
 * Real query-backed counts only; agency + tenant-portfolio scoped.
 */
class DashboardLeasingMetrics
{
    public function forActor(?User $actor, ?array $propertyIds): array
    {
        $tenantIds = TenantAccess::accessibleTenantIds($actor);

        $tenants = Tenant::query();
        $leases = Lease::query();

        if (! is_null($tenantIds)) {
            $tenants->whereIn('id', $tenantIds);
            $leases->whereIn('tenant_id', $tenantIds);
        }
        // AgencyScope already constrains non-super-admins to their agency.

        $activeLeases = (clone $leases)->where('status', 'active');

        return [
            'total_tenants' => (clone $tenants)->count(),
            'active_leases' => (clone $activeLeases)->count(),
            'leases_expiring_soon' => (clone $activeLeases)
                ->whereDate('end_date', '<=', now()->addDays(30)->toDateString())
                ->count(),
            'leases_by_status' => (clone $leases)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all(),
        ];
    }
}
