<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Property;
use Tests\TestCase;

class PropertyTest extends TestCase
{
    public function test_property_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $response = $this->postJson('/api/v1/properties', [
            'name' => 'Test Plaza',
            'property_type' => 'commercial',
            'address' => '123 Test Road',
            'city' => 'Karachi',
            'postal_code' => '75500',
            'description' => 'A test property.',
        ], $this->bearer($token));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test Plaza')
            ->assertJsonPath('data.property_type', 'commercial');

        $this->assertDatabaseHas('properties', [
            'name' => 'Test Plaza',
            'city' => 'Karachi',
        ]);
    }

    public function test_property_creation_validates_required_fields(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/properties', [
            'name' => '',
            'property_type' => 'castle', // invalid
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'property_type', 'address', 'city']);
    }

    public function test_property_retrieval(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $this->getJson("/api/v1/properties/{$property->id}", $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Gulshan Residency')
            ->assertJsonStructure(['data' => ['id', 'name', 'buildings']]);
    }

    public function test_property_update(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        $this->putJson("/api/v1/properties/{$property->id}", [
            'city' => 'Hyderabad',
            'notes' => 'Updated notes.',
        ], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.city', 'Hyderabad');

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'city' => 'Hyderabad',
        ]);
    }

    public function test_property_archive_cascades_to_buildings_and_units(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();
        $buildingIds = $property->buildings()->pluck('id')->all();
        $unitIds = $property->units()->pluck('id')->all();
        $this->assertNotEmpty($buildingIds);
        $this->assertNotEmpty($unitIds);

        $this->deleteJson("/api/v1/properties/{$property->id}", [], $this->bearer($token))
            ->assertOk();

        // Property + children are soft-deleted (archived), not destroyed.
        $this->assertSoftDeleted('properties', ['id' => $property->id]);
        foreach ($buildingIds as $bid) {
            $this->assertSoftDeleted('buildings', ['id' => $bid]);
        }
        foreach ($unitIds as $uid) {
            $this->assertSoftDeleted('units', ['id' => $uid]);
        }

        // Archived property disappears from the list.
        $list = $this->getJson('/api/v1/properties', $this->bearer($token))->json('data');
        $this->assertNotContains($property->id, collect($list)->pluck('id')->all());
    }

    public function test_property_restore_brings_back_children(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $property = Property::where('name', 'DHA Trade Tower')->firstOrFail();
        $this->deleteJson("/api/v1/properties/{$property->id}", [], $this->bearer($token))->assertOk();

        $this->postJson("/api/v1/properties/{$property->id}/restore", [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'DHA Trade Tower');

        $this->assertDatabaseHas('properties', ['id' => $property->id, 'deleted_at' => null]);
        $this->assertEquals(
            0,
            \App\Domains\Property\Models\Building::onlyTrashed()->where('property_id', $property->id)->count()
        );
    }

    public function test_property_list_supports_search_and_filters(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->getJson('/api/v1/properties?search=Gulshan', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gulshan Residency');

        $response = $this->getJson('/api/v1/properties?property_type=commercial', $this->bearer($token))
            ->assertOk();

        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $p) {
            $this->assertEquals('commercial', $p['property_type']);
        }
    }

    public function test_property_creation_is_audited(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/properties', [
            'name' => 'Audit Villa',
            'property_type' => 'residential',
            'address' => '1 Audit Lane',
            'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'properties.create',
            'entity_type' => Property::class,
        ]);
    }
}
