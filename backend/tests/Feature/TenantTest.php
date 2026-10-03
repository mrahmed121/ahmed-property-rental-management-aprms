<?php

namespace Tests\Feature;

use App\Domains\Leasing\Models\Tenant;
use Tests\TestCase;

class TenantTest extends TestCase
{
    public function test_tenant_crud(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // Create
        $create = $this->postJson('/api/v1/tenants', [
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => 'test.tenant@example.com',
            'phone' => '0300-0000000',
            'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();
        $id = $create->json('data.id');
        $this->assertEquals('prospective', $create->json('data.status'));

        // Read
        $this->getJson("/api/v1/tenants/{$id}", $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Test Tenant');

        // Update
        $this->putJson("/api/v1/tenants/{$id}", [
            'phone' => '0300-9999999',
            'kyc_status' => 'verified',
        ], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.kyc_status', 'verified');

        // Archive (soft delete)
        $this->deleteJson("/api/v1/tenants/{$id}", [], $this->bearer($token))->assertOk();
        $this->assertSoftDeleted('tenants', ['id' => $id]);

        // Archived tenant disappears from list
        $ids = collect($this->getJson('/api/v1/tenants', $this->bearer($token))->json('data'))
            ->pluck('id')->all();
        $this->assertNotContains($id, $ids);
    }

    public function test_tenant_validation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/tenants', [
            'first_name' => '',
            'email' => 'not-an-email',
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    public function test_tenant_agency_isolation(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        // Create a tenant in agency B directly.
        $agencyB = \App\Domains\Shared\Models\Agency::where('slug', 'second-agency')->firstOrFail();
        $tenantB = Tenant::create([
            'agency_id' => $agencyB->id,
            'first_name' => 'Second',
            'last_name' => 'Agency',
        ]);

        // A cannot list, read, update or archive B's tenant.
        $ids = collect($this->getJson('/api/v1/tenants', $this->bearer($tokenA))->json('data'))
            ->pluck('id')->all();
        $this->assertNotContains($tenantB->id, $ids);

        $this->getJson("/api/v1/tenants/{$tenantB->id}", $this->bearer($tokenA))->assertNotFound();
        $this->putJson("/api/v1/tenants/{$tenantB->id}", ['city' => 'x'], $this->bearer($tokenA))->assertNotFound();
        $this->deleteJson("/api/v1/tenants/{$tenantB->id}", [], $this->bearer($tokenA))->assertNotFound();
        $this->assertFalse($tenantB->fresh()->trashed());

        $tenantB->forceDelete();
    }

    public function test_tenant_search_and_filters(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->getJson('/api/v1/tenants?search=Raza', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ahmed Raza');

        $response = $this->getJson('/api/v1/tenants?status=active', $this->bearer($token))->assertOk();
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $t) {
            $this->assertEquals('active', $t['status']);
        }
    }

    public function test_tenant_creation_is_audited(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/tenants', [
            'first_name' => 'Audit',
            'last_name' => 'Tenant',
        ], $this->bearer($token))->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenants.create',
            'entity_type' => Tenant::class,
        ]);
    }
}
