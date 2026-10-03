<?php

namespace Tests\Feature;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Utilities\Models\UtilityMeter;
use Tests\TestCase;

class P6SecurityTest extends TestCase
{
    public function test_utility_agency_isolation(): void
    {
        $meter = UtilityMeter::firstOrFail();
        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/utility/meters/{$meter->id}", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/utility/meters', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($meter->id, $ids);

        $this->getJson('/api/v1/utility/bills', $this->bearer($tokenB))->assertOk();
    }

    public function test_expense_agency_isolation(): void
    {
        $expense = Expense::firstOrFail();
        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/expenses/{$expense->id}", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/expenses', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($expense->id, $ids);
    }

    public function test_utility_tenant_scope(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        // Tenant sees own unit meter.
        $meters = $this->getJson('/api/v1/utility/meters', $this->bearer($token))->assertOk()->json('data');
        $this->assertNotEmpty($meters);

        // Tenant cannot create meters.
        $this->postJson('/api/v1/utility/meters', [], $this->bearer($token))->assertForbidden();
        // Tenant cannot generate bills.
        $this->postJson('/api/v1/utility/meters/1/bills', [], $this->bearer($token))->assertForbidden();
    }

    public function test_utility_owner_scope(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');

        $meters = $this->getJson('/api/v1/utility/meters', $this->bearer($token))->assertOk()->json('data');
        $this->assertIsArray($meters);

        $this->postJson('/api/v1/utility/meters', [], $this->bearer($token))->assertForbidden();
    }

    public function test_expense_tenant_denied(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        $this->getJson('/api/v1/expenses', $this->bearer($token))->assertForbidden();
    }

    public function test_expense_technician_denied(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');

        $this->getJson('/api/v1/expenses', $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/expenses', [], $this->bearer($token))->assertForbidden();
    }

    public function test_p6_auditor_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $meter = UtilityMeter::firstOrFail();
        $expense = Expense::firstOrFail();

        $this->getJson('/api/v1/utility/meters', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/utility/meters/{$meter->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/utility/bills', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/expenses', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/expenses/{$expense->id}", $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/utility/meters', [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/expenses', [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/expenses/{$expense->id}/transition", ['to' => 'approved'], $this->bearer($token))->assertForbidden();
    }

    public function test_concurrent_bill_protection(): void
    {
        // UNIQUE(agency_id, meter_id, period_start) + idempotent finalize
        // prevent duplicate finalized bills.
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = \App\Domains\Property\Models\Property::firstOrFail();

        $res = $this->postJson('/api/v1/utility/meters', [
            'property_id' => $property->id,
            'meter_number' => 'TEST-EL-CONCURRENT',
            'utility_type' => 'electricity',
        ], $this->bearer($token))->assertCreated()->json('data');
        $meterId = $res['id'];

        $this->postJson("/api/v1/utility/meters/{$meterId}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 500,
        ], $this->bearer($token))->assertCreated();

        $bill = $this->postJson("/api/v1/utility/meters/{$meterId}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 20,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        // Finalize again is idempotent.
        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        $count = \App\Domains\Utilities\Models\UtilityBill::where('meter_id', $meterId)
            ->where('status', 'finalized')->count();
        $this->assertEquals(1, $count);
    }
}
