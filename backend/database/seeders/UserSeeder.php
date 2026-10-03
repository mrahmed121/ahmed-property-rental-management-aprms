<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\Role;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo users for local development. ALL credentials are clearly fake/demo.
 * Password for every demo user: password123
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password123';

    public function run(): void
    {
        // Platform Super Admin (no agency).
        $superRole = Role::withoutAgencyScope()->where('slug', 'super-admin')->firstOrFail();
        $super = User::firstOrCreate(
            ['email' => 'super@aprms.local'],
            ['name' => 'Super Admin', 'agency_id' => null, 'password' => self::DEMO_PASSWORD, 'is_active' => true]
        );
        $super->roles()->syncWithoutDetaching([$superRole->id]);

        // Ahmed Estates — one demo user per role.
        $agencyA = Agency::where('slug', 'ahmed-estates')->firstOrFail();
        $this->makeAgencyUsers($agencyA, 'ahmedestates.local');

        // Second agency — admin only (isolation tests).
        $agencyB = Agency::where('slug', 'second-agency')->firstOrFail();
        $this->makeUser($agencyB, 'Second Agency Admin', 'admin@secondagency.local', ['agency-admin']);
    }

    private function makeAgencyUsers(Agency $agency, string $domain): void
    {
        // [display name, email local part, role slug]
        $map = [
            ['Agency Admin', 'admin', 'agency-admin'],
            ['Property Manager', 'manager', 'property-manager'],
            ['Accountant', 'accountant', 'accountant'],
            ['Maintenance Supervisor', 'supervisor', 'maintenance-supervisor'],
            ['Technician', 'technician', 'technician'],
            ['Owner Demo', 'owner', 'owner'],
            ['Tenant Demo', 'tenant', 'tenant'],
            ['Auditor Demo', 'auditor', 'auditor'],
        ];

        foreach ($map as [$name, $local, $roleSlug]) {
            $this->makeUser($agency, $name, "{$local}@{$domain}", [$roleSlug]);
        }
    }

    private function makeUser(Agency $agency, string $name, string $email, array $roleSlugs): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'agency_id' => $agency->id,
                'password' => self::DEMO_PASSWORD,
                'is_active' => true,
            ]
        );

        $roleIds = Role::withoutAgencyScope()
            ->where('agency_id', $agency->id)
            ->whereIn('slug', $roleSlugs)
            ->pluck('id');

        $user->roles()->syncWithoutDetaching($roleIds);
    }
}
