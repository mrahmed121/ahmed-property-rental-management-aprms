<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\Permission;
use App\Domains\Shared\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /** P1 permission catalogue. P2 appends the property domain. */
    public const PERMISSIONS = [
        ['slug' => 'dashboard.view', 'name' => 'View dashboard', 'group' => 'dashboard'],
        ['slug' => 'users.view', 'name' => 'View users', 'group' => 'users'],
        ['slug' => 'users.manage', 'name' => 'Create/update/delete users', 'group' => 'users'],
        ['slug' => 'roles.view', 'name' => 'View roles', 'group' => 'roles'],
        ['slug' => 'roles.manage', 'name' => 'Create/update roles', 'group' => 'roles'],
        ['slug' => 'settings.view', 'name' => 'View settings', 'group' => 'settings'],
        ['slug' => 'settings.manage', 'name' => 'Update settings', 'group' => 'settings'],
        ['slug' => 'audit.view', 'name' => 'View audit logs', 'group' => 'audit'],
        ['slug' => 'reports.view', 'name' => 'View reports', 'group' => 'reports'],
        // P2 — Property domain
        ['slug' => 'properties.view', 'name' => 'View properties', 'group' => 'properties'],
        ['slug' => 'properties.manage', 'name' => 'Create/update/archive properties', 'group' => 'properties'],
        ['slug' => 'buildings.view', 'name' => 'View buildings', 'group' => 'buildings'],
        ['slug' => 'buildings.manage', 'name' => 'Create/update/archive buildings', 'group' => 'buildings'],
        ['slug' => 'units.view', 'name' => 'View units', 'group' => 'units'],
        ['slug' => 'units.manage', 'name' => 'Create/update/archive units', 'group' => 'units'],
        ['slug' => 'documents.view', 'name' => 'View property documents', 'group' => 'documents'],
        ['slug' => 'documents.manage', 'name' => 'Upload/delete property documents', 'group' => 'documents'],
        // P3 — Leasing domain
        ['slug' => 'tenants.view', 'name' => 'View tenants', 'group' => 'tenants'],
        ['slug' => 'tenants.manage', 'name' => 'Create/update/archive tenants', 'group' => 'tenants'],
        ['slug' => 'applications.view', 'name' => 'View tenant applications', 'group' => 'applications'],
        ['slug' => 'applications.manage', 'name' => 'Review tenant applications', 'group' => 'applications'],
        ['slug' => 'screening.view', 'name' => 'View screening records', 'group' => 'screening'],
        ['slug' => 'screening.manage', 'name' => 'Run screening and KYC decisions', 'group' => 'screening'],
        ['slug' => 'leases.view', 'name' => 'View leases', 'group' => 'leases'],
        ['slug' => 'leases.manage', 'name' => 'Create/activate/renew/terminate leases', 'group' => 'leases'],
        ['slug' => 'inspections.view', 'name' => 'View move-out inspections', 'group' => 'inspections'],
        ['slug' => 'inspections.manage', 'name' => 'Record/review move-out inspections', 'group' => 'inspections'],
    ];

    /**
     * Role => permission slugs. Exactly the 9 approved roles.
     * '*' means all permissions.
     */
    public const ROLE_MATRIX = [
        'super-admin' => ['*'],
        'agency-admin' => ['dashboard.view', 'users.view', 'users.manage', 'roles.view', 'roles.manage', 'settings.view', 'settings.manage', 'audit.view', 'reports.view', 'properties.view', 'properties.manage', 'buildings.view', 'buildings.manage', 'units.view', 'units.manage', 'documents.view', 'documents.manage', 'tenants.view', 'tenants.manage', 'applications.view', 'applications.manage', 'screening.view', 'screening.manage', 'leases.view', 'leases.manage', 'inspections.view', 'inspections.manage'],
        'property-manager' => ['dashboard.view', 'users.view', 'settings.view', 'audit.view', 'reports.view', 'properties.view', 'properties.manage', 'buildings.view', 'buildings.manage', 'units.view', 'units.manage', 'documents.view', 'documents.manage', 'tenants.view', 'tenants.manage', 'applications.view', 'applications.manage', 'screening.view', 'screening.manage', 'leases.view', 'leases.manage', 'inspections.view', 'inspections.manage'],
        'accountant' => ['dashboard.view', 'settings.view', 'audit.view', 'reports.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view', 'tenants.view', 'applications.view', 'screening.view', 'leases.view'],
        'maintenance-supervisor' => ['dashboard.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view', 'inspections.view'],
        'technician' => ['dashboard.view', 'properties.view', 'buildings.view', 'units.view'],
        'owner' => ['dashboard.view', 'settings.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view', 'tenants.view', 'applications.view', 'leases.view', 'inspections.view'],
        'tenant' => ['dashboard.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view', 'tenants.view', 'applications.view', 'leases.view', 'inspections.view'],
        'auditor' => ['dashboard.view', 'users.view', 'settings.view', 'audit.view', 'reports.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view', 'tenants.view', 'applications.view', 'screening.view', 'leases.view', 'inspections.view'],
    ];

    public const ROLE_NAMES = [
        'super-admin' => 'Super Admin',
        'agency-admin' => 'Agency Admin',
        'property-manager' => 'Property Manager',
        'accountant' => 'Accountant',
        'maintenance-supervisor' => 'Maintenance Supervisor',
        'technician' => 'Technician',
        'owner' => 'Owner',
        'tenant' => 'Tenant',
        'auditor' => 'Auditor',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::PERMISSIONS as $perm) {
                Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
            }
            $allSlugs = Permission::pluck('slug')->all();

            // System role: platform level, no agency.
            $this->makeRole(null, 'super-admin', true, $allSlugs);

            // Agency roles: one set per agency.
            foreach (Agency::all() as $agency) {
                foreach (self::ROLE_MATRIX as $slug => $slugs) {
                    if ($slug === 'super-admin') {
                        continue;
                    }
                    $resolved = $slugs === ['*'] ? $allSlugs : $slugs;
                    $this->makeRole($agency->id, $slug, false, $resolved);
                }
            }
        });
    }

    private function makeRole(?int $agencyId, string $slug, bool $isSystem, array $permissionSlugs): void
    {
        $role = Role::withoutAgencyScope()->firstOrCreate(
            ['agency_id' => $agencyId, 'slug' => $slug],
            [
                'name' => self::ROLE_NAMES[$slug],
                'description' => $isSystem ? 'Platform-level full access.' : 'Agency role: '.self::ROLE_NAMES[$slug].'.',
                'is_system' => $isSystem,
            ]
        );

        $ids = Permission::whereIn('slug', $permissionSlugs)->pluck('id');
        $role->permissions()->sync($ids);
    }
}
