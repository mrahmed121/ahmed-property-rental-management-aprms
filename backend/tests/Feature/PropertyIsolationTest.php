<?php

namespace Tests\Feature;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use Tests\TestCase;

class PropertyIsolationTest extends TestCase
{
    public function test_cross_agency_property_list_isolation(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        $names = collect($this->getJson('/api/v1/properties', $this->bearer($tokenA))->json('data'))
            ->pluck('name')->all();

        $this->assertContains('Gulshan Residency', $names);
        $this->assertNotContains('Model Town Villas', $names);
    }

    public function test_cross_agency_property_read_returns_404(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $propertyB = Property::withoutAgencyScope()->where('name', 'Model Town Villas')->firstOrFail();

        $this->getJson("/api/v1/properties/{$propertyB->id}", $this->bearer($tokenA))->assertNotFound();
    }

    public function test_cross_agency_property_update_returns_404(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $propertyB = Property::withoutAgencyScope()->where('name', 'Model Town Villas')->firstOrFail();

        $this->putJson("/api/v1/properties/{$propertyB->id}", ['city' => 'Hacked'], $this->bearer($tokenA))
            ->assertNotFound();

        $this->assertNotEquals('Hacked', $propertyB->fresh()->city);
    }

    public function test_cross_agency_property_archive_returns_404(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $propertyB = Property::withoutAgencyScope()->where('name', 'Model Town Villas')->firstOrFail();

        $this->deleteJson("/api/v1/properties/{$propertyB->id}", [], $this->bearer($tokenA))->assertNotFound();
        $this->assertFalse($propertyB->fresh()->trashed());
    }

    public function test_nested_cross_agency_protection(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        $buildingB = Building::withoutAgencyScope()->where('name', 'Villa Block')->firstOrFail();
        $unitB = Unit::withoutAgencyScope()->where('unit_number', 'V-101')->firstOrFail();

        // Nested reads.
        $this->getJson("/api/v1/buildings/{$buildingB->id}", $this->bearer($tokenA))->assertNotFound();
        $this->getJson("/api/v1/units/{$unitB->id}", $this->bearer($tokenA))->assertNotFound();

        // Nested writes.
        $this->putJson("/api/v1/units/{$unitB->id}", ['status' => 'vacant'], $this->bearer($tokenA))
            ->assertNotFound();
        $this->deleteJson("/api/v1/buildings/{$buildingB->id}", [], $this->bearer($tokenA))
            ->assertNotFound();

        // Filtered lists hide B's records.
        $unitIds = collect($this->getJson('/api/v1/units', $this->bearer($tokenA))->json('data'))
            ->pluck('id')->all();
        $this->assertNotContains($unitB->id, $unitIds);
    }

    public function test_owner_sees_only_own_properties(): void
    {
        $tokenOwner = $this->loginAs('owner@ahmedestates.local');

        $names = collect($this->getJson('/api/v1/properties', $this->bearer($tokenOwner))->json('data'))
            ->pluck('name')->all();

        // Owner owns Gulshan Residency + Bahria Greens Estate (seeded), not DHA Trade Tower.
        $this->assertContains('Gulshan Residency', $names);
        $this->assertContains('Bahria Greens Estate', $names);
        $this->assertNotContains('DHA Trade Tower', $names);
        $this->assertNotContains('Model Town Villas', $names);
    }

    public function test_owner_cannot_open_unowned_property(): void
    {
        $tokenOwner = $this->loginAs('owner@ahmedestates.local');
        $tower = Property::where('name', 'DHA Trade Tower')->firstOrFail();

        $this->getJson("/api/v1/properties/{$tower->id}", $this->bearer($tokenOwner))->assertNotFound();
    }

    public function test_owner_cannot_create_property(): void
    {
        $tokenOwner = $this->loginAs('owner@ahmedestates.local');

        $this->postJson('/api/v1/properties', [
            'name' => 'Owner Sneak',
            'property_type' => 'residential',
            'address' => 'x',
            'city' => 'Karachi',
        ], $this->bearer($tokenOwner))->assertForbidden();
    }

    public function test_tenant_sees_no_properties_in_p2(): void
    {
        $tokenTenant = $this->loginAs('tenant@ahmedestates.local');

        $response = $this->getJson('/api/v1/properties', $this->bearer($tokenTenant))->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_super_admin_sees_all_properties(): void
    {
        $token = $this->loginAs('super@aprms.local');

        $names = collect($this->getJson('/api/v1/properties', $this->bearer($token))->json('data'))
            ->pluck('name')->all();

        $this->assertContains('Gulshan Residency', $names);
        $this->assertContains('Model Town Villas', $names);
    }
}
