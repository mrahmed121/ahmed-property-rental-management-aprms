<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'aprms-api')
            ->assertJsonStructure([
                'status', 'service', 'version',
                'database' => ['ok', 'latency_ms', 'driver'],
                'timestamp',
            ]);

        $this->assertTrue($response->json('database.ok'));
    }
}
