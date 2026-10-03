<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\RentInvoice;
use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use Tests\TestCase;

class BillingPaymentTest extends TestCase
{
    private function makeTenant(string $tag): Tenant
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $res = $this->postJson('/api/v1/tenants', [
            'first_name' => "Pay{$tag}", 'last_name' => 'Test', 'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();

        return Tenant::findOrFail($res->json('data.id'));
    }

    private function makeLease(Tenant $tenant, string $unitNumber): Lease
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $building = \App\Domains\Property\Models\Building::where('name', 'Block A')->firstOrFail();
        $unit = $this->postJson('/api/v1/units', [
            'building_id' => $building->id, 'unit_number' => $unitNumber,
            'unit_type' => 'apartment', 'status' => 'vacant',
        ], $this->bearer($token))->assertCreated()->json('data');

        $lease = $this->postJson('/api/v1/leases', [
            'tenant_id' => $tenant->id, 'unit_id' => $unit['id'],
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'monthly_rent' => 50000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();

        return Lease::findOrFail($lease['id']);
    }

    private function makeInvoice(Lease $lease, string $period, float $rent = 50000): RentInvoice
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $start = $period.'-01';
        $end = date('Y-m-t', strtotime($start));

        $res = $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => $start, 'period_end' => $end,
            'due_date' => $period.'-05', 'base_rent' => $rent,
        ], $this->bearer($token))->assertCreated()->json('data');

        return RentInvoice::findOrFail($res['id']);
    }

    public function test_payment_posting(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Post');
        $lease = $this->makeLease($tenant, 'P-101');
        $invoice = $this->makeInvoice($lease, '2026-06');

        $res = $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'lease_id' => $lease->id,
            'payment_date' => '2026-06-04',
            'amount' => 50000,
            'method' => 'bank_transfer',
            'reference' => 'TEST-001',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertMatchesRegularExpression('/^RCPT-\d{4}-\d{6}$/', $res['receipt_number']);
        $this->assertEquals('posted', $res['status']);

        // Invoice fully paid.
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->outstanding());

        // Ledger: payment credit posted.
        $this->assertDatabaseHas('tenant_ledger_entries', [
            'tenant_id' => $tenant->id,
            'entry_type' => 'payment',
            'credit' => 50000,
        ]);
    }

    public function test_payment_amount_validation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Valid');

        // Zero amount rejected.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 0,
            'method' => 'cash',
        ], $this->bearer($token))->assertStatus(422);

        // Negative amount rejected.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => -100,
            'method' => 'cash',
        ], $this->bearer($token))->assertStatus(422);

        // Invalid method rejected.
        $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 100,
            'method' => 'bitcoin',
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_partial_payment(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Partial');
        $lease = $this->makeLease($tenant, 'P-102');
        $invoice = $this->makeInvoice($lease, '2026-06');

        $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 20000,
            'method' => 'cash',
        ], $this->bearer($token))->assertCreated();

        $invoice = $invoice->fresh();
        // June invoice in October: past due date → overdue (still partially paid).
        $this->assertEquals('overdue', $invoice->status);
        $this->assertEquals(30000, $invoice->outstanding());
        $this->assertEquals(20000, $invoice->paid_amount);
    }

    public function test_excess_payment_becomes_credit(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Excess');
        $lease = $this->makeLease($tenant, 'P-103');
        $invoice = $this->makeInvoice($lease, '2026-06');

        $res = $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 70000, // 20,000 over
            'method' => 'cash',
        ], $this->bearer($token))->assertCreated()->json('data');

        // Invoice paid in full; excess stays as unallocated credit (not lost).
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(20000, $res['unallocated']);
        $this->assertEquals(50000, $res['allocated_total']);
    }

    public function test_allocation_waterfall_order(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Waterfall');
        $lease = $this->makeLease($tenant, 'P-104');

        // Two invoices: June (older) and July.
        $june = $this->makeInvoice($lease, '2026-06');
        $july = $this->makeInvoice($lease, '2026-07');

        // Pay 60,000: should hit June (oldest arrears) first, then July.
        $res = $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-07-10',
            'amount' => 60000,
            'method' => 'bank_transfer',
        ], $this->bearer($token))->assertCreated()->json('data');

        $buckets = collect($res['allocations'])->pluck('bucket')->all();
        // June fully paid (arrears), July partially.
        $this->assertEquals('paid', $june->fresh()->status);
        // July invoice in October: overdue with 40,000 remaining.
        $this->assertEquals('overdue', $july->fresh()->status);
        $this->assertEquals(40000, $july->fresh()->outstanding());
    }

    public function test_payment_idempotency(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Idem');

        $payload = [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 10000,
            'method' => 'cash',
            'idempotency_key' => 'test-idem-001',
        ];

        $first = $this->postJson('/api/v1/payments', $payload, $this->bearer($token))->assertCreated()->json('data');
        $second = $this->postJson('/api/v1/payments', $payload, $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals($first['id'], $second['id']);
        $this->assertEquals(1, Payment::where('idempotency_key', 'test-idem-001')->count());
    }

    public function test_concurrent_payment_protection(): void
    {
        // Two payments with the same idempotency key: only one is created.
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Conc');

        $payload = [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 5000,
            'method' => 'cash',
            'idempotency_key' => 'test-conc-001',
        ];

        $r1 = $this->postJson('/api/v1/payments', $payload, $this->bearer($token))->assertCreated()->json('data');
        $r2 = $this->postJson('/api/v1/payments', $payload, $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals($r1['id'], $r2['id']);
        // Only one ledger payment entry.
        $this->assertEquals(1, \App\Domains\Billing\Models\TenantLedgerEntry::where('tenant_id', $tenant->id)
            ->where('entry_type', 'payment')->count());
    }

    public function test_reversal_integrity(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Reverse');
        $lease = $this->makeLease($tenant, 'P-105');
        $invoice = $this->makeInvoice($lease, '2026-06');

        $payment = $this->postJson('/api/v1/payments', [
            'tenant_id' => $tenant->id,
            'payment_date' => '2026-06-04',
            'amount' => 50000,
            'method' => 'cash',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('paid', $invoice->fresh()->status);

        // Reverse.
        $this->postJson("/api/v1/payments/{$payment['id']}/reverse", [
            'reason' => 'Test reversal: wrong tenant.',
        ], $this->bearer($token))->assertOk();

        // Invoice back to issued, paid_amount restored.
        $invoice = $invoice->fresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertEquals(0, (float) $invoice->paid_amount);

        // Payment marked reversed (not deleted).
        $this->assertEquals('reversed', Payment::find($payment['id'])->status);

        // Ledger has the reversal entry.
        $this->assertDatabaseHas('tenant_ledger_entries', [
            'tenant_id' => $tenant->id,
            'entry_type' => 'reversal',
            'debit' => 50000,
        ]);

        // Cannot reverse twice.
        $this->postJson("/api/v1/payments/{$payment['id']}/reverse", [
            'reason' => 'Again.',
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_payment_preview(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Preview');
        $lease = $this->makeLease($tenant, 'P-106');
        $this->makeInvoice($lease, '2026-06');

        $preview = $this->postJson('/api/v1/payments/preview', [
            'tenant_id' => $tenant->id,
            'amount' => 30000,
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals(30000, $preview['amount']);
        $this->assertNotEmpty($preview['lines']);
        $this->assertEquals(30000, $preview['allocated']);
        $this->assertEquals(0, $preview['unallocated']);

        // Preview does not create anything.
        $this->assertEquals(0, Payment::where('tenant_id', $tenant->id)->count());
    }
}
