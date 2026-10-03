<?php

namespace Database\Seeders;

use App\Domains\Shared\Models\User;
use App\Domains\Statements\Models\StatementPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * P7 demo: a statement period and one generated statement per owner
 * with real data (no fabricated numbers).
 */
class StatementSeeder extends Seeder
{
    public function run(): void
    {
        if (StatementPeriod::where('start_date', '2026-09-01')->exists()) {
            return; // idempotent
        }

        $admin = User::where('email', 'admin@ahmedestates.local')->firstOrFail();
        Auth::login($admin);

        $period = StatementPeriod::create([
            'agency_id' => $admin->agency_id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
            'notes' => 'Demo: September 2026.',
        ]);

        $owners = User::where('agency_id', $admin->agency_id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->get();

        $generation = app(\App\Domains\Statements\Services\StatementGenerationService::class);

        foreach ($owners as $owner) {
            $propertyCount = \App\Domains\Property\Models\Property::where('agency_id', $admin->agency_id)
                ->where('owner_id', $owner->id)->count();
            if ($propertyCount === 0) continue;

            $generation->generate($owner, $period);
        }

        Auth::logout();
    }
}
