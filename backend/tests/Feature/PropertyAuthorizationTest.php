<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use Tests\TestCase;

class PropertyAuthorizationTest extends TestCase
{
    public function test_auditor_is_read_only_on_property_domain(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();

        // Reads allowed.
        $this->getJson('/api/v1/properties', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/properties/{$property->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/units', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/documents', $this->bearer($token))->assertOk();

        // Every mutation forbidden.
        $this->postJson('/api/v1/properties', ['name' => 'x'], $this->bearer($token))->assertForbidden();
        $this->putJson("/api/v1/properties/{$property->id}", ['city' => 'x'], $this->bearer($token))->assertForbidden();
        $this->deleteJson("/api/v1/properties/{$property->id}", [], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/buildings', ['property_id' => $property->id, 'name' => 'x'], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/units', ['building_id' => 1, 'unit_number' => 'x', 'unit_type' => 'room'], $this->bearer($token))->assertForbidden();
    }

    public function test_technician_cannot_manage_properties(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');

        $this->getJson('/api/v1/properties', $this->bearer($token))->assertOk();
        $this->postJson('/api/v1/properties', ['name' => 'x'], $this->bearer($token))->assertForbidden();
    }

    public function test_property_manager_has_full_property_access(): void
    {
        $token = $this->loginAs('manager@ahmedestates.local');

        $create = $this->postJson('/api/v1/properties', [
            'name' => 'Manager Plaza',
            'property_type' => 'commercial',
            'address' => '7 Manager Rd',
            'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();
        $id = $create->json('data.id');

        $this->putJson("/api/v1/properties/{$id}", ['city' => 'Lahore'], $this->bearer($token))->assertOk();
        $this->deleteJson("/api/v1/properties/{$id}", [], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/properties/{$id}/restore", [], $this->bearer($token))->assertOk();
    }

    public function test_tenant_cannot_mutate_units(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');
        $unit = Unit::firstOrFail();

        // Tenant has units.view but the P2 portfolio is empty; mutation is forbidden regardless.
        $this->putJson("/api/v1/units/{$unit->id}", ['status' => 'vacant'], $this->bearer($token))
            ->assertForbidden();
    }

    public function test_unauthenticated_property_access_is_401(): void
    {
        $this->getJson('/api/v1/properties')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/stats')->assertUnauthorized();
    }
}
