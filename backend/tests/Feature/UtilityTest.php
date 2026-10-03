<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Property;
use App\Domains\Utilities\Models\UtilityBill;
use App\Domains\Utilities\Models\UtilityMeter;
use Tests\TestCase;

class UtilityTest extends TestCase
{
    public function test_meter_crud(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $res = $this->postJson('/api/v1/utility/meters', [
            'property_id' => $property->id,
            'meter_number' => 'TEST-EL-001',
            'utility_type' => 'electricity',
            'opening_reading' => 0,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('TEST-EL-001', $res['meter_number']);
        $this->assertEquals('active', $res['status']);

        $this->getJson("/api/v1/utility/meters/{$res['id']}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/utility/meters', $this->bearer($token))->assertOk();
    }

    public function test_reading_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-002');

        $res = $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1500,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals(1500, $res['reading_value']);
    }

    public function test_negative_reading_rejection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-003');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => -100,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_decreasing_reading_rejection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-004');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-08-31',
            'reading_value' => 2000,
        ], $this->bearer($token))->assertCreated();

        // Lower than previous → 422.
        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1500,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_duplicate_reading_prevention(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-005');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1000,
        ], $this->bearer($token))->assertCreated();

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1100,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_consumption_calculation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-006');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-08-31',
            'reading_value' => 1000,
        ], $this->bearer($token))->assertCreated();
        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1350,
        ], $this->bearer($token))->assertCreated();

        $res = $this->postJson("/api/v1/utility/meters/{$meter->id}/consumption", [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals(1000, $res['previous_reading']);
        $this->assertEquals(1350, $res['current_reading']);
        $this->assertEquals(350, $res['consumption']);
    }

    public function test_utility_bill_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-007');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-08-31',
            'reading_value' => 1000,
        ], $this->bearer($token))->assertCreated();
        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1200,
        ], $this->bearer($token))->assertCreated();

        // Preview: 200 × 25 + 500 = 5,500.
        $preview = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills/preview", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 25,
            'fixed_charge' => 500,
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals(200, $preview['consumption']);
        $this->assertEquals(5500, $preview['total']);

        // Generate.
        $bill = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 25,
            'fixed_charge' => 500,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('draft', $bill['status']);
        $this->assertEquals(5500, $bill['total']);
        $this->assertMatchesRegularExpression('/^UB-\d{4}-\d{6}$/', $bill['bill_number']);
    }

    public function test_duplicate_bill_prevention(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-008');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 500,
        ], $this->bearer($token))->assertCreated();

        $bill = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 20,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        // Second bill for same meter/period → 422.
        $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 20,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_vacant_unit_owner_absorption(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Self-contained: property-level meter (no unit) → vacant_owner allocation.
        $property = Property::firstOrFail();
        $res = $this->postJson('/api/v1/utility/meters', [
            'property_id' => $property->id,
            'meter_number' => 'TEST-WA-VACANT',
            'utility_type' => 'water',
            'unit_of_measure' => 'm3',
        ], $this->bearer($token))->assertCreated()->json('data');
        $meter = UtilityMeter::findOrFail($res['id']);

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 300,
        ], $this->bearer($token))->assertCreated();

        $bill = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 100,
            'allocation_method' => 'equal_split',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        $bill = $this->getJson("/api/v1/utility/bills/{$bill['id']}", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertNotEmpty($bill['allocations']);
        $vacant = collect($bill['allocations'])->firstWhere('allocation_type', 'vacant_owner');
        $this->assertNotNull($vacant, 'Vacant-unit owner absorption line must exist.');
        $this->assertNull($vacant['tenant']);
    }

    public function test_utility_ledger_integration(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Self-contained: meter on R-101 (active tenant lease) → bill → ledger.
        $meter = $this->makeMeterOnUnit('TEST-EL-LEDGER', 'R-101');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-08-31',
            'reading_value' => 1000,
        ], $this->bearer($token))->assertCreated();
        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 1200,
        ], $this->bearer($token))->assertCreated();

        $bill = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 25,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertNotNull($bill['tenant'], 'Bill should link to the active lease tenant.');

        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        // Ledger should contain the utility charge.
        $tenantId = $bill['tenant']['id'];
        $ledger = $this->getJson("/api/v1/tenants/{$tenantId}/ledger", $this->bearer($token))
            ->assertOk()->json('data');

        $utilityEntry = collect($ledger['entries'])->first(function ($e) use ($bill) {
            return str_contains($e['description'] ?? '', $bill['bill_number']);
        });
        $this->assertNotNull($utilityEntry, 'Utility charge must appear in tenant ledger.');
        $this->assertEquals('utility', $utilityEntry['entry_type']);
    }

    public function test_utility_bill_reversal(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $meter = $this->makeMeter('TEST-EL-009');

        $this->postJson("/api/v1/utility/meters/{$meter->id}/readings", [
            'reading_date' => '2026-09-30',
            'reading_value' => 800,
        ], $this->bearer($token))->assertCreated();

        $bill = $this->postJson("/api/v1/utility/meters/{$meter->id}/bills", [
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'rate' => 20,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/utility/bills/{$bill['id']}/finalize", [], $this->bearer($token))->assertOk();

        $reversed = $this->postJson("/api/v1/utility/bills/{$bill['id']}/reverse", [
            'reason' => 'Test reversal.',
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals('reversed', $reversed['status']);

        // Bill still exists (not hard-deleted).
        $this->assertDatabaseHas('utility_bills', ['id' => $bill['id'], 'status' => 'reversed']);
    }

    private function makeMeter(string $number): UtilityMeter
    {
        return $this->makeMeterOnUnit($number, null);
    }

    private function makeMeterOnUnit(string $number, ?string $unitNumber): UtilityMeter
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $payload = [
            'property_id' => $property->id,
            'meter_number' => $number,
            'utility_type' => 'electricity',
        ];
        if ($unitNumber) {
            $unit = \App\Domains\Property\Models\Unit::where('unit_number', $unitNumber)->firstOrFail();
            $payload['unit_id'] = $unit->id;
            $payload['property_id'] = $unit->property_id;
        }

        $res = $this->postJson('/api/v1/utility/meters', $payload, $this->bearer($token))
            ->assertCreated()->json('data');

        return UtilityMeter::findOrFail($res['id']);
    }
}
