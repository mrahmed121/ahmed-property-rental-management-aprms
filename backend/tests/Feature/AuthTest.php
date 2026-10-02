<?php

namespace Tests\Feature;

use App\Domains\Shared\Models\AuditLog;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_with_valid_credentials_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@ahmedestates.local',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.email', 'admin@ahmedestates.local')
            ->assertJsonStructure([
                'data' => [
                    'token', 'token_type', 'expires_in',
                    'user' => ['id', 'name', 'email', 'agency', 'roles', 'permissions'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertContains('agency-admin', $response->json('data.user.roles'));
        // Password hash must never leak.
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@ahmedestates.local',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_with_unknown_email_returns_401(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_protected_route_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_with_valid_token_returns_current_user(): void
    {
        $token = $this->loginAs('manager@ahmedestates.local');

        $this->getJson('/api/v1/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.email', 'manager@ahmedestates.local')
            ->assertJsonPath('data.agency.name', 'Ahmed Estates');
    }

    public function test_logout_invalidates_token(): void
    {
        $token = $this->loginAs('manager@ahmedestates.local');

        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))->assertOk();

        // Reusing the token must now fail.
        $this->getJson('/api/v1/me', $this->bearer($token))->assertUnauthorized();
    }

    public function test_successful_login_creates_audit_entry(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@ahmedestates.local',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'entity_type' => \App\Domains\Shared\Models\User::class,
        ]);
    }

    public function test_failed_login_creates_audit_entry_without_actor(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@ahmedestates.local',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $entry = AuditLog::where('action', 'auth.failed_login')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertNull($entry->user_id);
    }
}
