<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (demo data for local development).
     */
    public function run(): void
    {
        $this->call([
            AgencySeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            PropertySeeder::class,
            LeasingSeeder::class,
            BillingSeeder::class,
            DepositSeeder::class,
            MaintenanceSeeder::class,
            UtilityExpenseSeeder::class,
        ]);
    }
}
