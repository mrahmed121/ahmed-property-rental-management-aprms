<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_auditor_cannot_create_users_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_tenant_cannot_create_users(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky2@example.com',
            'password' => 'password123',
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_auditor_can_read_users(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');

        $this->getJson('/api/v1/users', $this->bearer($token))->assertOk();
    }

    public function test_auditor_cannot_update_settings(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');

        $this->putJson('/api/v1/settings', [
            'settings' => [['key' => 'currency', 'value' => 'USD', 'type' => 'string']],
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_agency_admin_can_create_user_in_own_agency(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Manager',
            'email' => 'newmanager@ahmedestates.local',
            'password' => 'password123',
            'role_slugs' => ['property-manager'],
        ], $this->bearer($token));

        $response->assertCreated()
            ->assertJsonPath('data.email', 'newmanager@ahmedestates.local')
            ->assertJsonPath('data.agency.name', 'Ahmed Estates');

        $this->assertDatabaseHas('users', ['email' => 'newmanager@ahmedestates.local']);
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => 'Evil',
            'email' => 'evil@ahmedestates.local',
            'password' => 'password123',
            'role_slugs' => ['super-admin'],
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_technician_cannot_view_audit_logs(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');

        $this->getJson('/api/v1/audit-logs', $this->bearer($token))->assertForbidden();
    }

    public function test_auditor_can_view_audit_logs(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');

        $this->getJson('/api/v1/audit-logs', $this->bearer($token))->assertOk();
    }
}
