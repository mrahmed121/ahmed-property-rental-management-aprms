<?php

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\Property;
use App\Domains\Shared\Models\User;

/**
 * PropertyAccess — role-based portfolio scoping for the Property domain.
 *
 * Agency isolation is handled by AgencyScope. This adds the second layer:
 * owners see only their own properties; tenants see none in P2 (P3 leases
 * will link tenants to units); portfolio roles see the whole agency.
 *
 * Returns:
 *   null  => full agency access (no ownership restriction)
 *   []    => no property access at all
 *   [ids] => restricted to these property IDs
 */
class PropertyAccess
{
    private const PORTFOLIO_ROLES = [
        'agency-admin',
        'property-manager',
        'accountant',
        'maintenance-supervisor',
        'technician',
        'auditor',
    ];

    public static function accessiblePropertyIds(?User $actor): ?array
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

        if ($actor->hasRole('owner')) {
            return Property::withoutAgencyScope()
                ->where('agency_id', $actor->agency_id)
                ->where('owner_id', $actor->id)
                ->pluck('id')
                ->all();
        }

        // Tenant (P3 will link via leases) and any other role: nothing in P2.
        return [];
    }

    /** Filter a property/building/unit/document query to the actor's portfolio. */
    public static function applyToPropertyQuery($query, ?User $actor, string $propertyKey = 'id')
    {
        $ids = self::accessiblePropertyIds($actor);

        if (is_null($ids)) {
            return $query;
        }

        return $query->whereIn($propertyKey, $ids);
    }
}
