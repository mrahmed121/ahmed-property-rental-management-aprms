<?php

namespace Database\Seeders;

use App\Domains\Billing\Services\DunningService;
use App\Domains\Billing\Services\LateFeeService;
use App\Domains\Billing\Services\PaymentService;
use App\Domains\Billing\Services\RentInvoiceService;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic APRMS demo financial data (local development only).
 * Built THROUGH the domain services so ledger, allocations and
 * balances stay internally consistent.
 */
class BillingSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: skip if demo financial data already exists.
        if (\App\Domains\Billing\Models\RentInvoice::where('invoice_number', 'like', 'INV-2026-%')->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@ahmedestates.local')->firstOrFail();
        Auth::login($admin);

        $invoices = app(RentInvoiceService::class);
        $payments = app(PaymentService::class);

        $raza = Lease::with('tenant')->whereHas('tenant', fn ($q) => $q->where('first_name', 'Ahmed'))->where('status', 'active')->firstOrFail();
        $fatima = Lease::with('tenant')->whereHas('tenant', fn ($q) => $q->where('first_name', 'Fatima'))->where('status', 'active')->firstOrFail();

        // --- Ahmed Raza: September fully paid, October current ---
        $sepRaza = $invoices->create($raza, [
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'issue_date' => '2026-09-01', 'due_date' => '2026-09-05',
            'base_rent' => 40000, 'notes' => 'Demo: September rent.',
        ]);
        $payments->record($raza->tenant, [
            'lease_id' => $raza->id,
            'payment_date' => '2026-09-04',
            'amount' => 40000, 'method' => 'bank_transfer',
            'reference' => 'DEMO-SEPT-RAZA', 'notes' => 'Demo: full September payment.',
            'idempotency_key' => 'demo-raza-sep-001',
        ]);

        $octRaza = $invoices->create($raza, [
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'issue_date' => '2026-10-01', 'due_date' => '2026-10-05',
            'base_rent' => 40000, 'notes' => 'Demo: October rent (current).',
        ]);

        // --- Fatima Khan: August overdue + late fee, September partial ---
        $augFatima = $invoices->create($fatima, [
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'issue_date' => '2026-08-01', 'due_date' => '2026-08-05',
            'base_rent' => 85000, 'utilities' => 3500,
            'notes' => 'Demo: August rent + utilities (overdue).',
        ]);
        // Late fee accrues (past grace).
        app(LateFeeService::class)->accrueForInvoice($augFatima->fresh());
        // Dunning reminders for the overdue invoice.
        app(DunningService::class)->processOverdue();

        $sepFatima = $invoices->create($fatima, [
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'issue_date' => '2026-09-01', 'due_date' => '2026-09-05',
            'base_rent' => 85000, 'notes' => 'Demo: September rent.',
        ]);
        // Partial payment: 50,000 of 85,000 → waterfall hits oldest arrears first.
        $payments->record($fatima->tenant, [
            'lease_id' => $fatima->id,
            'payment_date' => '2026-09-10',
            'amount' => 50000, 'method' => 'cash',
            'reference' => 'DEMO-SEPT-FATIMA', 'notes' => 'Demo: partial September payment.',
            'idempotency_key' => 'demo-fatima-sep-001',
        ]);

        // Refresh overdue flags.
        $invoices->refreshOverdue();

        Auth::logout();
    }
}
