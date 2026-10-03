<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use Tests\TestCase;

class BuildingUnitTest extends TestCase
{
    public function test_building_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $response = $this->postJson('/api/v1/buildings', [
            'property_id' => $property->id,
            'name' => 'Block C',
            'floors' => 6,
        ], $this->bearer($token));

        $response->assertCreated()->assertJsonPath('data.name', 'Block C');

        $this->assertDatabaseHas('buildings', [
            'name' => 'Block C',
            'property_id' => $property->id,
            'agency_id' => $property->agency_id,
        ]);
    }

    public function test_building_property_relationship_is_enforced(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Cross-agency property_id must not leak or link.
        $otherAgencyProperty = Property::withoutAgencyScope()
            ->where('name', 'Model Town Villas')->firstOrFail();

        $this->postJson('/api/v1/buildings', [
            'property_id' => $otherAgencyProperty->id,
            'name' => 'Sneaky Block',
        ], $this->bearer($token))->assertNotFound();
    }

    public function test_building_name_unique_per_property(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $this->postJson('/api/v1/buildings', [
            'property_id' => $property->id,
            'name' => 'Block A', // already exists
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_building_under_archived_property(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();
        $this->deleteJson("/api/v1/properties/{$property->id}", [], $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/buildings', [
            'property_id' => $property->id,
            'name' => 'Ghost Block',
        ], $this->bearer($token))->assertStatus(404); // archived => invisible
    }

    public function test_unit_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $building = Building::where('name', 'Block A')->firstOrFail();

        $response = $this->postJson('/api/v1/units', [
            'building_id' => $building->id,
            'unit_number' => 'A-301',
            'unit_type' => 'apartment',
            'floor' => 3,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area_sqft' => 1150,
            'market_rent' => 48000,
            'status' => 'vacant',
        ], $this->bearer($token));

        $response->assertCreated()->assertJsonPath('data.unit_number', 'A-301');

        $unit = Unit::where('unit_number', 'A-301')->firstOrFail();
        $this->assertEquals($building->id, $unit->building_id);
        $this->assertEquals($building->property_id, $unit->property_id); // derived, consistent
        $this->assertEquals($building->agency_id, $unit->agency_id);
    }

    public function test_unit_building_relationship_is_enforced(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $otherAgencyBuilding = Building::withoutAgencyScope()
            ->where('name', 'Villa Block')->firstOrFail();

        $this->postJson('/api/v1/units', [
            'building_id' => $otherAgencyBuilding->id,
            'unit_number' => 'X-999',
            'unit_type' => 'apartment',
        ], $this->bearer($token))->assertNotFound();
    }

    public function test_duplicate_unit_number_rejected_within_building(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $building = Building::where('name', 'Block A')->firstOrFail();

        $this->postJson('/api/v1/units', [
            'building_id' => $building->id,
            'unit_number' => 'A-101', // already exists in Block A
            'unit_type' => 'apartment',
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_number']);
    }

    public function test_same_unit_number_allowed_in_different_building(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $buildingB = Building::where('name', 'Block B')->firstOrFail();

        // A-101 exists in Block A; it must be allowed in Block B.
        $this->postJson('/api/v1/units', [
            'building_id' => $buildingB->id,
            'unit_number' => 'A-101',
            'unit_type' => 'apartment',
        ], $this->bearer($token))->assertCreated();
    }

    public function test_unit_update_and_status_change(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $unit = Unit::where('unit_number', 'A-101')->firstOrFail();

        $this->putJson("/api/v1/units/{$unit->id}", [
            'status' => 'reserved',
            'market_rent' => 50000,
        ], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved');

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'reserved']);
    }

    public function test_building_archive_cascades_to_units(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $building = Building::where('name', 'Block B')->firstOrFail();
        $unitIds = $building->units()->pluck('id')->all();

        $this->deleteJson("/api/v1/buildings/{$building->id}", [], $this->bearer($token))->assertOk();

        $this->assertSoftDeleted('buildings', ['id' => $building->id]);
        foreach ($unitIds as $uid) {
            $this->assertSoftDeleted('units', ['id' => $uid]);
        }
    }

    public function test_invalid_relationship_rejected(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Non-existent building.
        $this->postJson('/api/v1/units', [
            'building_id' => 999999,
            'unit_number' => 'Z-1',
            'unit_type' => 'apartment',
        ], $this->bearer($token))->assertStatus(422);

        // Invalid enum values.
        $building = Building::where('name', 'Block A')->firstOrFail();
        $this->postJson('/api/v1/units', [
            'building_id' => $building->id,
            'unit_number' => 'Z-2',
            'unit_type' => 'penthouse', // invalid
            'status' => 'haunted',      // invalid
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_type', 'status']);
    }
}
