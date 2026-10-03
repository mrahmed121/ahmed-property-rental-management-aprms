<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Shared\Models\Agency;
use Tests\TestCase;

class BillingSecurityTest extends TestCase
{
    public function test_cross_agency_isolation(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $invoice = RentInvoice::firstOrFail();
        $payment = Payment::firstOrFail();

        // Agency B admin cannot see A's financials.
        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/invoices/{$invoice->id}", $this->bearer($tokenB))->assertNotFound();
        $this->getJson("/api/v1/payments/{$payment->id}", $this->bearer($tokenB))->assertNotFound();
        $this->getJson("/api/v1/payments/{$payment->id}/receipt", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/invoices', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($invoice->id, $ids);

        // Cannot post payment for A's tenant.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $invoice->tenant_id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
        ], $this->bearer($tokenB))->assertNotFound();
    }

    public function test_tenant_self_scope(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local'); // Ahmed Raza
        $ownInvoice = RentInvoice::whereHas('tenant', fn ($q) => $q->where('first_name', 'Ahmed'))->firstOrFail();
        $otherInvoice = RentInvoice::whereHas('tenant', fn ($q) => $q->where('first_name', 'Fatima'))->firstOrFail();

        // Sees own invoices.
        $this->getJson("/api/v1/invoices/{$ownInvoice->id}", $this->bearer($token))->assertOk();

        // Cannot see another tenant's invoice.
        $this->getJson("/api/v1/invoices/{$otherInvoice->id}", $this->bearer($token))->assertNotFound();

        // Cannot record payments.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $ownInvoice->tenant_id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
        ], $this->bearer($token))->assertForbidden();

        // Can view own ledger.
        $this->getJson("/api/v1/tenants/{$ownInvoice->tenant_id}/ledger", $this->bearer($token))->assertOk();

        // Cannot view another tenant's ledger.
        $this->getJson("/api/v1/tenants/{$otherInvoice->tenant_id}/ledger", $this->bearer($token))->assertNotFound();
    }

    public function test_owner_scope(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');

        // Owner sees invoices for owned properties (Bahria Greens).
        $invoices = $this->getJson('/api/v1/invoices', $this->bearer($token))->assertOk()->json('data');
        $this->assertNotEmpty($invoices);
        foreach ($invoices as $inv) {
            $this->assertNotNull($inv['property']);
        }

        // Cannot record payments.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $invoices[0]['tenant']['id'],
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_auditor_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $invoice = RentInvoice::firstOrFail();
        $payment = Payment::firstOrFail();

        $this->getJson('/api/v1/invoices', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/invoices/{$invoice->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/payments', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/financial/dashboard', $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/invoices', [], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/payments', [], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/rent-cycle/generate', ['period' => '2027-02'], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/payments/{$payment->id}/reverse", ['reason' => 'x'], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/financial/periods/lock', ['period' => '2026-08'], $this->bearer($token))->assertForbidden();
    }

    public function test_permission_restrictions(): void
    {
        // Property manager can view but NOT record payments (financial staff only).
        $token = $this->loginAs('manager@ahmedestates.local');

        $this->getJson('/api/v1/invoices', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/financial/dashboard', $this->bearer($token))->assertOk();

        $tenant = \App\Domains\Leasing\Models\Tenant::firstOrFail();
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
        ], $this->bearer($token))->assertForbidden();

        $this->postJson('/api/v1/rent-cycle/generate', ['period' => '2027-02'], $this->bearer($token))->assertForbidden();
    }

    public function test_period_lock(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Lock August 2026.
        $this->postJson('/api/v1/financial/periods/lock', ['period' => '2026-08'], $this->bearer($token))
            ->assertOk();

        // Cannot create an invoice in the locked period.
        $lease = Lease::where('status', 'active')->firstOrFail();
        $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2026-08-15',
            'period_end' => '2026-08-31',
            'due_date' => '2026-08-20',
            'base_rent' => 1000,
        ], $this->bearer($token))->assertStatus(422);

        // Unlock works.
        $this->postJson('/api/v1/financial/periods/unlock', ['period' => '2026-08'], $this->bearer($token))
            ->assertOk();

        // Cannot lock current/future period.
        $this->postJson('/api/v1/financial/periods/lock', ['period' => now()->format('Y-m')], $this->bearer($token))
            ->assertStatus(422);
    }

    public function test_receipt_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $payment = Payment::firstOrFail();

        $res = $this->getJson("/api/v1/payments/{$payment->id}/receipt", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertEquals($payment->receipt_number, $res['receipt_number']);
        $this->assertNotEmpty($res['tenant']['name']);
        $this->assertNotEmpty($res['allocations']);
        $this->assertArrayHasKey('tenant_balance', $res);
        $this->assertEquals('PKR', $res['currency']);

        // PDF downloads.
        $pdf = $this->get("/api/v1/payments/{$payment->id}/receipt/pdf", $this->bearer($token));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_financial_dashboard(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $res = $this->getJson('/api/v1/financial/dashboard', $this->bearer($token))
            ->assertOk()->json('data');

        // All metrics present and numeric.
        foreach (['billed_this_period', 'collected_this_period', 'outstanding', 'overdue', 'invoices_due', 'invoices_overdue', 'payments_today'] as $key) {
            $this->assertArrayHasKey($key, $res);
            $this->assertIsNumeric($res[$key]);
        }
        $this->assertArrayHasKey('arrears_aging', $res);
        $this->assertArrayHasKey('0_30', $res['arrears_aging']);

        // Outstanding = sum of (total - paid_amount) for non-void invoices.
        $expected = RentInvoice::where('status', '!=', 'void')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as v')->value('v');
        $this->assertEquals(round((float) $expected, 2), $res['outstanding']);
    }

    public function test_tenant_dashboard_scoped(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        $res = $this->getJson('/api/v1/financial/dashboard', $this->bearer($token))
            ->assertOk()->json('data');

        // Tenant sees only their own numbers (Ahmed Raza: 40k outstanding).
        $this->assertEquals(40000, $res['outstanding']);
    }
}
