<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Services\PropertyAccess;
use App\Domains\Shared\Models\User;

/**
 * TenantAccess — who may see which tenants (and therefore their
 * applications, leases and inspections).
 *
 * Returns:
 *   null  => full agency access
 *   []    => no tenant access at all
 *   [ids] => restricted to these tenant IDs
 */
class TenantAccess
{
    private const PORTFOLIO_ROLES = [
        'agency-admin',
        'property-manager',
        'accountant',
        'auditor',
    ];

    public static function accessibleTenantIds(?User $actor): ?array
    {
        if (! $actor) {
            return [];
        }

        if ($actor->isSuperAdmin()) {
            return null;
        }

        foreach (self::PORTFOLIO_ROLES as $role) {
            if ($actor->hasRole($role)) {
                return null;
            }
        }

        // Owner: tenants with leases or applications in properties they own.
        if ($actor->hasRole('owner')) {
            $propertyIds = PropertyAccess::accessiblePropertyIds($actor);
            if (is_null($propertyIds)) {
                return null;
            }
            if (empty($propertyIds)) {
                return [];
            }

            return Tenant::withoutAgencyScope()
                ->where('agency_id', $actor->agency_id)
                ->where(function ($q) use ($propertyIds) {
                    $q->whereHas('leases', fn ($qq) => $qq->whereIn('property_id', $propertyIds))
                      ->orWhereHas('applications', fn ($qq) => $qq->whereIn('property_id', $propertyIds));
                })
                ->pluck('id')
                ->all();
        }

        // Tenant portal: own record only (linked via user_id).
        if ($actor->hasRole('tenant')) {
            $tenant = Tenant::withoutAgencyScope()
                ->where('agency_id', $actor->agency_id)
                ->where('user_id', $actor->id)
                ->first();

            return $tenant ? [$tenant->id] : [];
        }

        return [];
    }
}
