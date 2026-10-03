<?php

namespace Database\Seeders;

use App\Domains\Deposits\Models\Deposit;
use App\Domains\Deposits\Services\DepositService;
use App\Domains\Deposits\Services\DepositSettlementService;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\MoveOutInspection;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic P5 deposit demo data (local development only).
 * Built through the domain services for consistency.
 */
class DepositSeeder extends Seeder
{
    public function run(): void
    {
        if (Deposit::where('reference', 'like', 'DEMO-%')->exists()) {
            return; // idempotent
        }

        $admin = User::where('email', 'admin@ahmedestates.local')->firstOrFail();
        Auth::login($admin);

        $deposits = app(DepositService::class);
        $settlements = app(DepositSettlementService::class);

        // Ahmed Raza (active lease): deposit held.
        $razaLease = Lease::with('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('first_name', 'Ahmed'))
            ->where('status', 'active')->firstOrFail();

        $razaDeposit = $deposits->create($razaLease, [
            'deposit_amount' => 80000, // 2× rent, within cap
            'reference' => 'DEMO-DEP-RAZA',
            'notes' => 'Demo: security deposit (2 months rent).',
        ]);
        $deposits->receive($razaDeposit, [
            'amount' => 80000,
            'received_date' => '2026-09-03',
            'reason' => 'Demo: deposit received at lease activation.',
        ]);

        // Fatima Khan (active lease): deposit held, smaller.
        $fatimaLease = Lease::with('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('first_name', 'Fatima'))
            ->where('status', 'active')->firstOrFail();

        $fatimaDeposit = $deposits->create($fatimaLease, [
            'deposit_amount' => 100000,
            'reference' => 'DEMO-DEP-FATIMA',
            'notes' => 'Demo: security deposit.',
        ]);
        $deposits->receive($fatimaDeposit, [
            'amount' => 100000,
            'received_date' => '2025-11-03',
            'reason' => 'Demo: deposit received at lease activation.',
        ]);

        // Terminated lease (Sara Malik): deposit + inspection + settled.
        $saraLease = Lease::with('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('first_name', 'Sara'))
            ->first();
        if ($saraLease) {
            $saraDeposit = $deposits->create($saraLease, [
                'deposit_amount' => 76000,
                'reference' => 'DEMO-DEP-SARA',
                'notes' => 'Demo: settled deposit for terminated lease.',
            ]);
            $deposits->receive($saraDeposit->fresh(), [
                'amount' => 76000,
                'received_date' => '2025-08-03',
                'reason' => 'Demo: deposit received.',
            ]);

            $inspection = MoveOutInspection::where('lease_id', $saraLease->id)->first();
            if ($inspection && $inspection->review_status !== 'reviewed') {
                $inspection->update(['review_status' => 'reviewed', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            }
            if ($inspection) {
                // A damage deduction + a wear observation (not charged).
                $d1 = $settlements->proposeDeduction($saraDeposit->fresh(), [
                    'category' => 'damage',
                    'assessment' => 'damage',
                    'description' => 'Demo: broken bedroom window pane.',
                    'amount' => 8000,
                    'inspection_id' => $inspection->id,
                ]);
                $settlements->reviewDeduction($d1, 'approved');

                $d2 = $settlements->proposeDeduction($saraDeposit->fresh(), [
                    'category' => 'cleaning',
                    'assessment' => 'wear',
                    'description' => 'Demo: light wall scuffs — normal wear, not charged.',
                    'amount' => 1500,
                    'inspection_id' => $inspection->id,
                ]);
                $settlements->reviewDeduction($d2, 'rejected');

                $settlement = $settlements->draft($saraDeposit->fresh(), $inspection->id);
                $settlements->finalize($settlement, ['apply_to_balance' => 0]);
            }
        }

        Auth::logout();
    }
}
