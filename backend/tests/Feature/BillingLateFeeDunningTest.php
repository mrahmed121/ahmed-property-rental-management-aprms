<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\DunningReminder;
use App\Domains\Billing\Models\LateFee;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use Tests\TestCase;

class BillingLateFeeDunningTest extends TestCase
{
    private function overdueInvoice(): RentInvoice
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = Lease::where('status', 'active')->firstOrFail();

        $res = $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05', // long past + grace
            'base_rent' => 40000,
        ], $this->bearer($token))->assertCreated()->json('data');

        return RentInvoice::findOrFail($res['id']);
    }

    public function test_late_fee_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $invoice = $this->overdueInvoice();

        $res = $this->postJson('/api/v1/late-fees/accrue', [], $this->bearer($token))
            ->assertOk()->json();

        $this->assertStringContainsString('late fee(s) accrued', $res['message']);

        $fee = LateFee::where('invoice_id', $invoice->id)->firstOrFail();
        // 5% of 40,000 = 2,000 (cap 5,000).
        $this->assertEquals(2000, (float) $fee->amount);
        $this->assertEquals('accrued', $fee->status);
        $this->assertEquals('percent', $fee->rule_snapshot['late_fee_type']);

        // Invoice total increased.
        $this->assertEquals(42000, (float) $invoice->fresh()->total);

        // Ledger debit.
        $this->assertDatabaseHas('tenant_ledger_entries', [
            'entry_type' => 'late_fee',
            'debit' => 2000,
        ]);
    }

    public function test_late_fee_idempotency(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $invoice = $this->overdueInvoice();

        $this->postJson('/api/v1/late-fees/accrue', [], $this->bearer($token))->assertOk();
        $this->postJson('/api/v1/late-fees/accrue', [], $this->bearer($token))->assertOk();

        $this->assertEquals(1, LateFee::where('invoice_id', $invoice->id)->count());
    }

    public function test_late_fee_cap(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $lease = Lease::where('status', 'active')->firstOrFail();

        // Huge invoice: 5% would exceed the 5,000 cap.
        $res = $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-05',
            'base_rent' => 200000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson('/api/v1/late-fees/accrue', [], $this->bearer($token))->assertOk();

        $fee = LateFee::where('invoice_id', $res['id'])->firstOrFail();
        $this->assertEquals(5000, (float) $fee->amount); // capped
    }

    public function test_dunning_cadence(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $invoice = $this->overdueInvoice(); // due 2026-05-05, now 2026-10-03 → 151 days overdue

        $res = $this->postJson('/api/v1/dunning/process', [], $this->bearer($token))
            ->assertOk()->json();

        // All 4 stages (3, 7, 15, 30 days) should be scheduled.
        $stages = DunningReminder::where('invoice_id', $invoice->id)->pluck('stage')->sort()->values()->all();
        $this->assertEquals(['day_15', 'day_3', 'day_30', 'day_7'], $stages);

        // Idempotent: second run creates nothing new.
        $this->postJson('/api/v1/dunning/process', [], $this->bearer($token))->assertOk();
        $this->assertEquals(4, DunningReminder::where('invoice_id', $invoice->id)->count());
    }

    public function test_dunning_mark_sent(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $invoice = $this->overdueInvoice();

        $this->postJson('/api/v1/dunning/process', [], $this->bearer($token))->assertOk();
        $reminder = DunningReminder::where('invoice_id', $invoice->id)->firstOrFail();

        $this->postJson("/api/v1/dunning/{$reminder->id}/sent", [], $this->bearer($token))
            ->assertOk();

        $this->assertEquals('sent', $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->sent_at);

        // Idempotent.
        $this->postJson("/api/v1/dunning/{$reminder->id}/sent", [], $this->bearer($token))->assertOk();
    }

    public function test_ledger_balance(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = \App\Domains\Leasing\Models\Tenant::where('first_name', 'Ahmed')->firstOrFail();

        // Ahmed Raza seed: Sep invoice 40,000 (paid) + Oct invoice 40,000 (unpaid).
        // Balance = 80,000 debits - 40,000 credits = 40,000.
        $res = $this->getJson("/api/v1/tenants/{$tenant->id}/ledger/balance", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertEquals(40000, $res['balance']);
        $this->assertEquals('PKR', $res['currency']);
    }

    public function test_ledger_statement(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = \App\Domains\Leasing\Models\Tenant::where('first_name', 'Ahmed')->firstOrFail();

        $res = $this->getJson("/api/v1/tenants/{$tenant->id}/ledger", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertNotEmpty($res['entries']);
        $this->assertEquals($res['closing_balance'], $res['opening_balance'] + $res['total_debit'] - $res['total_credit']);

        // Running balances are sequential.
        $prev = $res['opening_balance'];
        foreach ($res['entries'] as $e) {
            $expected = round($prev + $e['debit'] - $e['credit'], 2);
            $this->assertEquals($expected, $e['balance_after']);
            $prev = $e['balance_after'];
        }
    }
}
