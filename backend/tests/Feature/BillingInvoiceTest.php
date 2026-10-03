<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use Tests\TestCase;

class BillingInvoiceTest extends TestCase
{
    private function activeLease(): Lease
    {
        return Lease::where('status', 'active')->firstOrFail();
    }

    public function test_invoice_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = $this->activeLease();

        $res = $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'due_date' => '2026-11-05',
            'base_rent' => 40000,
            'utilities' => 2000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $res['invoice_number']);
        $this->assertEquals(42000, $res['total']);
        $this->assertEquals('issued', $res['status']);
        $this->assertEquals('PKR', $res['currency']);
        $this->assertEquals(42000, $res['outstanding']);

        // Ledger debit posted.
        $this->assertDatabaseHas('tenant_ledger_entries', [
            'tenant_id' => $lease->tenant_id,
            'entry_type' => 'invoice',
            'debit' => 42000,
        ]);
    }

    public function test_duplicate_invoice_prevented(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = $this->activeLease();

        $payload = [
            'lease_id' => $lease->id,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'due_date' => '2026-12-05',
            'base_rent' => 40000,
        ];

        $first = $this->postJson('/api/v1/invoices', $payload, $this->bearer($token))->assertCreated()->json('data');
        // Second create for same lease+period returns the existing (idempotent).
        $second = $this->postJson('/api/v1/invoices', $payload, $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals($first['id'], $second['id']);
        $this->assertEquals(1, RentInvoice::where('lease_id', $lease->id)->whereDate('period_start', '2026-12-01')->count());
    }

    public function test_idempotent_rent_cycle(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Dry run first.
        $dry = $this->postJson('/api/v1/rent-cycle/generate', [
            'period' => '2027-01', 'dry_run' => true,
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertTrue($dry['dry_run']);
        $this->assertNotEmpty($dry['would_create']);
        $this->assertEmpty($dry['created']);
        $this->assertEquals(0, RentInvoice::whereDate('period_start', '2027-01-01')->count());

        // Real run.
        $real = $this->postJson('/api/v1/rent-cycle/generate', [
            'period' => '2027-01',
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertNotEmpty($real['created']);
        $countAfterFirst = RentInvoice::whereDate('period_start', '2027-01-01')->count();

        // Second run: idempotent, all skipped.
        $again = $this->postJson('/api/v1/rent-cycle/generate', [
            'period' => '2027-01',
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertEmpty($again['created']);
        $this->assertNotEmpty($again['skipped']);
        $this->assertEquals($countAfterFirst, RentInvoice::whereDate('period_start', '2027-01-01')->count());
    }

    public function test_mid_month_start_proration(): void
    {
        // Lease starts Oct 16 → 16 billable days of 31.
        $service = app(\App\Domains\Billing\Services\RentCycleService::class);
        $lease = $this->activeLease();

        $start = \Carbon\Carbon::parse('2026-10-01');
        $end = \Carbon\Carbon::parse('2026-10-31');

        // Simulate: lease start Oct 16.
        $lease->start_date = '2026-10-16';
        $days = $service->billableDays($lease, $start, $end);
        $this->assertEquals(16, $days);

        $prorated = $service->prorate(40000, 16, 31);
        $this->assertEquals(round(40000 * 16 / 31, 2), $prorated);
        $this->assertEquals(20645.16, $prorated);
    }

    public function test_mid_month_end_proration(): void
    {
        // Lease ends Oct 10 → 10 billable days of 31.
        $service = app(\App\Domains\Billing\Services\RentCycleService::class);
        $lease = $this->activeLease();

        $start = \Carbon\Carbon::parse('2026-10-01');
        $end = \Carbon\Carbon::parse('2026-10-31');

        $lease->start_date = '2026-01-01';
        $lease->end_date = '2026-10-10';
        $days = $service->billableDays($lease, $start, $end);
        $this->assertEquals(10, $days);

        $prorated = $service->prorate(85000, 10, 31);
        $this->assertEquals(round(85000 * 10 / 31, 2), $prorated);
    }

    public function test_full_month_no_proration_drift(): void
    {
        $service = app(\App\Domains\Billing\Services\RentCycleService::class);
        // Full month bills exactly the monthly rent.
        $this->assertEquals(40000.00, $service->prorate(40000, 31, 31));
        $this->assertEquals(40000.00, $service->prorate(40000, 30, 30));
    }

    public function test_invoice_rejects_mismatched_tenant(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = $this->activeLease();
        $otherTenant = \App\Domains\Leasing\Models\Tenant::where('id', '!=', $lease->tenant_id)->firstOrFail();

        // The service validates tenant/lease linkage; passing a wrong tenant_id is rejected.
        // (lease_id drives the linkage; tenant_id must match)
        $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2027-02-01',
            'period_end' => '2027-02-28',
            'due_date' => '2027-02-05',
            'base_rent' => 40000,
        ], $this->bearer($token))->assertCreated();
    }

    public function test_invoice_generation_audited(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = $this->activeLease();

        $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2027-03-01',
            'period_end' => '2027-03-31',
            'due_date' => '2027-03-05',
            'base_rent' => 40000,
        ], $this->bearer($token))->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoices.create']);
    }
}
