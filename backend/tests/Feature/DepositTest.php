<?php

namespace Tests\Feature;

use App\Domains\Deposits\Models\Deposit;
use App\Domains\Deposits\Models\DepositSettlement;
use App\Domains\Leasing\Models\Lease;
use Tests\TestCase;

class DepositTest extends TestCase
{
    private function activeLease(): Lease
    {
        return Lease::where('status', 'active')->firstOrFail();
    }

    public function test_deposit_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Use a lease without a deposit (create a fresh tenant+lease).
        $tenant = $this->makeTenant('DepCreate');
        $lease = $this->makeLease($tenant, 'D-101', 50000);

        $res = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id,
            'deposit_amount' => 100000,
            'reference' => 'TEST-DEP-001',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals(100000, $res['deposit_amount']);
        $this->assertEquals('required', $res['status']);
        $this->assertEquals('PKR', $res['currency']);
        $this->assertEquals(0, $res['held_amount']);
    }

    public function test_deposit_cap_enforced(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepCap');
        $lease = $this->makeLease($tenant, 'D-102', 50000); // cap = 150,000

        // Exact cap: allowed.
        $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id,
            'deposit_amount' => 150000,
        ], $this->bearer($token))->assertCreated();

        // Above cap: rejected.
        $tenant2 = $this->makeTenant('DepCap2');
        $lease2 = $this->makeLease($tenant2, 'D-103', 50000);
        $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease2->id,
            'deposit_amount' => 150001,
        ], $this->bearer($token))->assertStatus(422);

        // Amount is not silently modified — the deposit was not created.
        $this->assertEquals(0, Deposit::where('lease_id', $lease2->id)->count());
    }

    public function test_deposit_receipt(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepRecv');
        $lease = $this->makeLease($tenant, 'D-104', 40000);

        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $res = $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000,
            'reason' => 'Test receipt.',
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals('held', $res['status']);
        $this->assertEquals(80000, $res['held_amount']);

        // Transaction history recorded.
        $this->assertDatabaseHas('deposit_transactions', [
            'deposit_id' => $dep['id'],
            'type' => 'received',
            'amount' => 80000,
        ]);
    }

    public function test_deposit_transaction_history(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepHist');
        $lease = $this->makeLease($tenant, 'D-105', 40000);

        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000, 'reason' => 'Received.',
        ], $this->bearer($token))->assertOk();

        $detail = $this->getJson("/api/v1/deposits/{$dep['id']}", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertNotEmpty($detail['transactions']);
        $this->assertEquals('received', $detail['transactions'][0]['type']);
    }

    public function test_deduction_requires_reason(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepReason');
        $lease = $this->makeLease($tenant, 'D-106', 40000);
        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000, 'reason' => 'Received.',
        ], $this->bearer($token))->assertOk();

        // Empty description rejected.
        $this->postJson("/api/v1/deposits/{$dep['id']}/deductions", [
            'category' => 'damage',
            'description' => '',
            'amount' => 5000,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_deduction_cannot_exceed_held(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepExceed');
        $lease = $this->makeLease($tenant, 'D-107', 40000);
        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000, 'reason' => 'Received.',
        ], $this->bearer($token))->assertOk();

        $this->postJson("/api/v1/deposits/{$dep['id']}/deductions", [
            'category' => 'damage',
            'description' => 'Too much.',
            'amount' => 90000,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_settlement_requires_reviewed_inspection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepInsp');
        $lease = $this->makeLease($tenant, 'D-108', 40000);
        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000, 'reason' => 'Received.',
        ], $this->bearer($token))->assertOk();

        // Terminate and create an inspection but don't review it.
        $this->postJson("/api/v1/leases/{$lease->id}/terminate", [
            'termination_date' => now()->toDateString(),
            'reason' => 'Test termination.',
        ], $this->bearer($token))->assertOk();

        $insp = $this->postJson('/api/v1/inspections', [
            'lease_id' => $lease->id,
            'inspection_date' => now()->toDateString(),
            'condition' => 'good',
        ], $this->bearer($token))->assertCreated()->json('data');

        // Draft without reviewed inspection → 422.
        $this->postJson("/api/v1/deposits/{$dep['id']}/settlement/draft", [
            'inspection_id' => $insp['id'],
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_full_settlement_flow(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('DepFull');
        $lease = $this->makeLease($tenant, 'D-109', 40000);

        $dep = $this->postJson('/api/v1/deposits', [
            'lease_id' => $lease->id, 'deposit_amount' => 80000,
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->postJson("/api/v1/deposits/{$dep['id']}/receive", [
            'amount' => 80000, 'reason' => 'Received.',
        ], $this->bearer($token))->assertOk();

        // Propose + approve a damage deduction.
        $ded = $this->postJson("/api/v1/deposits/{$dep['id']}/deductions", [
            'category' => 'damage',
            'assessment' => 'damage',
            'description' => 'Broken window.',
            'amount' => 10000,
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->assertEquals('proposed', $ded['status']);

        $this->postJson("/api/v1/deposits/deductions/{$ded['id']}/review", [
            'decision' => 'approved',
        ], $this->bearer($token))->assertOk();

        // Reviewed inspection (lease terminated first).
        $insp = $this->terminateAndInspect($lease);

        // Generate a rent invoice so the tenant has an outstanding balance.
        $this->postJson('/api/v1/invoices', [
            'lease_id' => $lease->id,
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'due_date' => '2026-02-05',
            'base_rent' => 40000,
        ], $this->bearer($token))->assertCreated();

        // Draft.
        $stl = $this->postJson("/api/v1/deposits/{$dep['id']}/settlement/draft", [
            'inspection_id' => $insp['id'],
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->assertEquals('draft', $stl['status']);

        // Preview: 80,000 − 10,000 − 5,000 applied = 65,000 refund.
        $preview = $this->postJson("/api/v1/deposits/{$dep['id']}/settlement/preview", [
            'apply_to_balance' => 5000,
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals(80000, $preview['gross_deposit']);
        $this->assertEquals(10000, $preview['total_deductions']);
        $this->assertEquals(5000, $preview['applied_to_balance']);
        $this->assertEquals(65000, $preview['refund_amount']);

        // Finalize.
        $final = $this->postJson("/api/v1/deposits/{$dep['id']}/settlement/finalize", [
            'apply_to_balance' => 5000,
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('finalized', $final['status']);
        $this->assertEquals(65000, $final['refund_amount']);

        // Deposit settled.
        $depFresh = $this->getJson("/api/v1/deposits/{$dep['id']}", $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('settled', $depFresh['status']);

        // Double finalize is idempotent (returns finalized, no duplicate).
        $again = $this->postJson("/api/v1/deposits/{$dep['id']}/settlement/finalize", [
            'apply_to_balance' => 5000,
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('finalized', $again['status']);
        $this->assertEquals(1, DepositSettlement::where('deposit_id', $dep['id'])->count());
    }

    public function test_settlement_lock_blocks_edits(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Use the seeded settled deposit (Sara Malik).
        $deposit = Deposit::where('status', 'settled')->first();
        if (! $deposit) $this->markTestSkipped('No settled deposit seeded.');

        // Cannot propose deductions on settled deposit.
        $this->postJson("/api/v1/deposits/{$deposit->id}/deductions", [
            'category' => 'damage',
            'description' => 'Late addition.',
            'amount' => 1000,
        ], $this->bearer($token))->assertStatus(422);

        // Cannot adjust settled deposit.
        $this->postJson("/api/v1/deposits/{$deposit->id}/adjust", [
            'deposit_amount' => 80000,
            'reason' => 'Test adjustment.',
        ], $this->bearer($token))->assertStatus(422);
    }

    private function terminateAndInspect($lease)
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Terminate the lease.
        $this->postJson("/api/v1/leases/{$lease->id}/terminate", [
            'termination_date' => now()->toDateString(),
            'reason' => 'Test termination.',
        ], $this->bearer($token))->assertOk();

        // Create and review the inspection.
        $insp = $this->postJson('/api/v1/inspections', [
            'lease_id' => $lease->id,
            'inspection_date' => now()->toDateString(),
            'condition' => 'good',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/inspections/{$insp['id']}/review", [], $this->bearer($token))->assertOk();

        return $insp;
    }

    // --- Helpers ---

    private function makeTenant(string $tag)
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $res = $this->postJson('/api/v1/tenants', [
            'first_name' => "Dep{$tag}", 'last_name' => 'Test', 'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();

        return \App\Domains\Leasing\Models\Tenant::findOrFail($res->json('data.id'));
    }

    private function makeLease($tenant, string $unitNumber, float $rent)
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
            'monthly_rent' => $rent,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();

        return \App\Domains\Leasing\Models\Lease::findOrFail($lease['id']);
    }
}
