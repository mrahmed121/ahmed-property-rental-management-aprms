<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\Permission;
use App\Domains\Shared\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /** P1 permission catalogue. Future phases append their own. */
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
    ];

    /**
     * Role => permission slugs. Exactly the 9 approved roles.
     * '*' means all permissions.
     */
    public const ROLE_MATRIX = [
        'super-admin' => ['*'],
        'agency-admin' => ['dashboard.view', 'users.view', 'users.manage', 'roles.view', 'roles.manage', 'settings.view', 'settings.manage', 'audit.view', 'reports.view'],
        'property-manager' => ['dashboard.view', 'users.view', 'settings.view', 'audit.view', 'reports.view'],
        'accountant' => ['dashboard.view', 'settings.view', 'audit.view', 'reports.view'],
        'maintenance-supervisor' => ['dashboard.view'],
        'technician' => ['dashboard.view'],
        'owner' => ['dashboard.view', 'settings.view'],
        'tenant' => ['dashboard.view'],
        'auditor' => ['dashboard.view', 'users.view', 'settings.view', 'audit.view', 'reports.view'],
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
