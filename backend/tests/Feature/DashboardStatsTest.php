<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    public function test_dashboard_stats_returns_real_counts(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $response = $this->getJson('/api/v1/dashboard/stats', $this->bearer($token))->assertOk();

        $data = $response->json('data');

        // Seeded agency A: 3 properties, 6 buildings, 20 units.
        $this->assertEquals(3, $data['total_properties']);
        $this->assertEquals(6, $data['total_buildings']);
        $this->assertEquals(20, $data['total_units']);

        // Vacant + occupied are real subsets.
        $this->assertGreaterThan(0, $data['vacant_units']);
        $this->assertGreaterThan(0, $data['occupied_units']);
        $this->assertEquals(
            $data['total_units'],
            array_sum($data['units_by_status'])
        );
    }

    public function test_dashboard_stats_are_agency_scoped(): void
    {
        $tokenB = $this->loginAs('admin@secondagency.local');

        $data = $this->getJson('/api/v1/dashboard/stats', $this->bearer($tokenB))
            ->assertOk()->json('data');

        // Agency B has exactly 1 property, 1 building, 2 units.
        $this->assertEquals(1, $data['total_properties']);
        $this->assertEquals(1, $data['total_buildings']);
        $this->assertEquals(2, $data['total_units']);
    }

    public function test_dashboard_stats_respect_owner_portfolio(): void
    {
        $tokenOwner = $this->loginAs('owner@ahmedestates.local');

        $data = $this->getJson('/api/v1/dashboard/stats', $this->bearer($tokenOwner))
            ->assertOk()->json('data');

        // Owner owns 2 of the 3 agency-A properties.
        $this->assertEquals(2, $data['total_properties']);
        $this->assertLessThan(20, $data['total_units']);
    }

    public function test_dashboard_stats_empty_for_tenant(): void
    {
        $tokenTenant = $this->loginAs('tenant@ahmedestates.local');

        $data = $this->getJson('/api/v1/dashboard/stats', $this->bearer($tokenTenant))
            ->assertOk()->json('data');

        $this->assertEquals(0, $data['total_properties']);
        $this->assertEquals(0, $data['total_units']);
    }
}
