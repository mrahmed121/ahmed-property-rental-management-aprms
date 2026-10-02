<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /** Sensible P1 defaults; billing rules are consumed by P4+ services. */
    public const DEFAULTS = [
        ['group' => 'general', 'key' => 'currency', 'value' => 'PKR', 'type' => 'string'],
        ['group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Karachi', 'type' => 'string'],
        ['group' => 'general', 'key' => 'date_format', 'value' => 'd-M-Y', 'type' => 'string'],
        ['group' => 'general', 'key' => 'agency_name', 'value' => null, 'type' => 'string'],
        ['group' => 'billing', 'key' => 'late_fee_type', 'value' => 'percent', 'type' => 'string'],
        ['group' => 'billing', 'key' => 'late_fee_value', 'value' => '5', 'type' => 'integer'],
        ['group' => 'billing', 'key' => 'late_fee_cap', 'value' => '5000', 'type' => 'integer'],
        ['group' => 'billing', 'key' => 'grace_days', 'value' => '3', 'type' => 'integer'],
        ['group' => 'billing', 'key' => 'management_fee_percent', 'value' => '10', 'type' => 'integer'],
        ['group' => 'billing', 'key' => 'rent_due_day', 'value' => '1', 'type' => 'integer'],
        ['group' => 'notifications', 'key' => 'reminder_days', 'value' => '[3,7,15,30]', 'type' => 'json'],
    ];

    public function run(): void
    {
        foreach (Agency::all() as $agency) {
            foreach (self::DEFAULTS as $def) {
                Setting::withoutAgencyScope()->firstOrCreate(
                    ['agency_id' => $agency->id, 'key' => $def['key']],
                    $def
                );
            }
        }
    }
}
