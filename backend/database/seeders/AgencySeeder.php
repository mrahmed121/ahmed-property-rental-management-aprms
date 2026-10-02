<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        Agency::firstOrCreate(
            ['slug' => 'ahmed-estates'],
            [
                'name' => 'Ahmed Estates',
                'email' => 'info@ahmedestates.local',
                'phone' => '+92-21-34567890',
                'address' => 'Main Boulevard, DHA Phase 6',
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'is_active' => true,
            ]
        );

        // Second agency exists ONLY to prove cross-agency isolation in tests.
        Agency::firstOrCreate(
            ['slug' => 'second-agency'],
            [
                'name' => 'Second Agency (Isolation Test)',
                'email' => 'info@secondagency.local',
                'city' => 'Lahore',
                'country' => 'Pakistan',
                'is_active' => true,
            ]
        );
    }
}
