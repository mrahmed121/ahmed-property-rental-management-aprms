<?php

namespace Database\Seeders;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Services\ApplicationService;
use App\Domains\Leasing\Services\LeaseService;
use App\Domains\Leasing\Services\LeaseTerminationService;
use App\Domains\Leasing\Services\MoveOutInspectionService;
use App\Domains\Leasing\Services\ScreeningService;
use App\Domains\Leasing\Services\TenantService;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic APRMS demo leasing data (local development only).
 * Built THROUGH the domain services (screening, activation, termination),
 * so unit occupancy and tenant statuses stay consistent.
 *
 * Demo leases live on Bahria Greens Estate units only, so P2's property
 * archive/restore coverage (Gulshan Residency, DHA Trade Tower) is untouched.
 */
class LeasingSeeder extends Seeder
{
    public function run(): void
    {
        $agencyA = Agency::where('slug', 'ahmed-estates')->firstOrFail();
        $admin = User::where('email', 'admin@ahmedestates.local')->firstOrFail();
        Auth::login($admin);

        $tenants = app(TenantService::class);
        $applications = app(ApplicationService::class);
        $screening = app(ScreeningService::class);
        $leases = app(LeaseService::class);

        $unitR101 = Unit::where('unit_number', 'R-101')->firstOrFail();
        $unitS02 = Unit::where('unit_number', 'S-02')->firstOrFail();

        // --- 1. Sara Malik: terminated lease + move-out inspection (runs first, frees R-101) ---
        $sara = $this->tenant($tenants, $agencyA, [
            'first_name' => 'Sara', 'last_name' => 'Malik',
            'email' => 'sara.malik.demo@example.com', 'city' => 'Lahore',
        ]);
        $appSara = $this->approvedApplication($applications, $screening, $agencyA, $sara, $unitR101);
        $leaseSara = $leases->create([
            'tenant_id' => $sara->id, 'unit_id' => $unitR101->id,
            'application_id' => $appSara->id,
            'start_date' => now()->subMonths(14)->toDateString(),
            'end_date' => now()->subMonths(2)->toDateString(),
            'monthly_rent' => 38000, 'deposit_amount' => 76000,
        ]);
        $leases->activate($leaseSara);
        app(LeaseTerminationService::class)->terminate(
            $leaseSara, now()->subMonths(2)->toDateString(), 'Demo: tenant relocated for work.'
        );
        app(MoveOutInspectionService::class)->create($leaseSara->id, [
            'inspection_date' => now()->subMonths(2)->addDay()->toDateString(),
            'condition' => 'good',
            'notes' => 'Demo inspection: minor paint touch-ups needed.',
            'damage_observations' => 'Small scuff on living-room wall.',
        ]);

        // --- 2. Ahmed Raza: active lease (linked to the tenant portal login) ---
        $raza = $this->tenant($tenants, $agencyA, [
            'first_name' => 'Ahmed', 'last_name' => 'Raza',
            'email' => 'ahmed.raza.demo@example.com', 'phone' => '0300-1112233',
            'city' => 'Lahore',
            'user_id' => User::where('email', 'tenant@ahmedestates.local')->first()?->id,
        ]);
        $appRaza = $this->approvedApplication($applications, $screening, $agencyA, $raza, $unitR101);
        $leaseRaza = $leases->create([
            'tenant_id' => $raza->id, 'unit_id' => $unitR101->id,
            'application_id' => $appRaza->id,
            'start_date' => now()->subMonths(1)->toDateString(),
            'end_date' => now()->addMonths(11)->toDateString(),
            'monthly_rent' => 40000, 'deposit_amount' => 80000,
            'terms' => 'Demo lease: 12 months, rent due by the 5th.',
        ]);
        $leases->activate($leaseRaza);

        // --- 3. Fatima Khan: active lease expiring soon (dashboard demo) ---
        $fatima = $this->tenant($tenants, $agencyA, [
            'first_name' => 'Fatima', 'last_name' => 'Khan',
            'email' => 'fatima.khan.demo@example.com', 'phone' => '0300-4445566',
            'city' => 'Lahore',
        ]);
        $appFatima = $this->approvedApplication($applications, $screening, $agencyA, $fatima, $unitS02);
        $leaseFatima = $leases->create([
            'tenant_id' => $fatima->id, 'unit_id' => $unitS02->id,
            'application_id' => $appFatima->id,
            'start_date' => now()->subMonths(11)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'monthly_rent' => 85000, 'deposit_amount' => 170000,
        ]);
        $leases->activate($leaseFatima);

        // --- 4. Bilal Sheikh: approved application, no lease yet ---
        $bilal = $this->tenant($tenants, $agencyA, [
            'first_name' => 'Bilal', 'last_name' => 'Sheikh',
            'email' => 'bilal.sheikh.demo@example.com', 'phone' => '0300-7778899',
            'city' => 'Lahore',
        ]);
        $this->approvedApplication($applications, $screening, $agencyA, $bilal, $unitR101);

        // --- 5. Usman Tariq: application under review (pipeline demo) ---
        $usman = $this->tenant($tenants, $agencyA, [
            'first_name' => 'Usman', 'last_name' => 'Tariq',
            'email' => 'usman.tariq.demo@example.com', 'city' => 'Lahore',
        ]);
        $appUsman = $applications->create([
            'tenant_id' => $usman->id,
            'property_id' => $unitS02->property_id,
            'unit_id' => $unitS02->id,
            'notes' => 'Demo: waiting on employer letter.',
        ]);
        $appUsman = $applications->transition($applications->find($appUsman->id), 'submitted');
        $applications->transition($applications->find($appUsman->id), 'under_review');

        Auth::logout();
    }

    private function tenant(TenantService $tenants, Agency $agency, array $attrs): Tenant
    {
        $existing = Tenant::withoutAgencyScope()
            ->where('agency_id', $agency->id)
            ->where('first_name', $attrs['first_name'])
            ->where('last_name', $attrs['last_name'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return $tenants->create([...$attrs, 'agency_id' => $agency->id]);
    }

    /** Drive an application through the full workflow to approved. */
    private function approvedApplication(
        ApplicationService $applications,
        ScreeningService $screening,
        Agency $agency,
        Tenant $tenant,
        Unit $unit,
    ) {
        $app = $applications->create([
            'tenant_id' => $tenant->id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->id,
            'agency_id' => $agency->id,
        ]);
        foreach (['submitted', 'under_review'] as $to) {
            $app = $applications->transition($applications->find($app->id), $to);
        }
        $screening->start($applications->find($app->id));
        $screening->decide(
            $applications->find($app->id),
            true,
            'Demo screening notes.',
            'verified'
        );

        return $applications->transition($applications->find($app->id), 'approved', [
            'decision_notes' => 'Demo approval.',
        ]);
    }
}
