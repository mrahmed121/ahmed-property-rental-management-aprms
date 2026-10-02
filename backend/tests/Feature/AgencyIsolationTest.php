<?php

namespace Tests\Feature;

use App\Domains\Shared\Models\User;
use Tests\TestCase;

class AgencyIsolationTest extends TestCase
{
    public function test_user_list_is_scoped_to_own_agency(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $response = $this->getJson('/api/v1/users', $this->bearer($token))->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();

        $this->assertContains('admin@ahmedestates.local', $emails);
        $this->assertNotContains('admin@secondagency.local', $emails);
        // Super Admin (platform user) must also be invisible to agency admins.
        $this->assertNotContains('super@aprms.local', $emails);
    }

    public function test_cross_agency_user_lookup_returns_404(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        $userB = User::where('email', 'admin@secondagency.local')->firstOrFail();

        // Existence of agency B's admin must not leak to agency A.
        $this->getJson("/api/v1/users/{$userB->id}", $this->bearer($tokenA))
            ->assertNotFound();
    }

    public function test_cross_agency_user_update_returns_404(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        $userB = User::where('email', 'admin@secondagency.local')->firstOrFail();

        $this->putJson("/api/v1/users/{$userB->id}", [
            'name' => 'Hijacked',
        ], $this->bearer($tokenA))->assertNotFound();

        $this->assertNotEquals('Hijacked', $userB->fresh()->name);
    }

    public function test_super_admin_sees_all_agencies(): void
    {
        $token = $this->loginAs('super@aprms.local');

        $response = $this->getJson('/api/v1/users', $this->bearer($token))->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->all();

        $this->assertContains('admin@ahmedestates.local', $emails);
        $this->assertContains('admin@secondagency.local', $emails);
    }

    public function test_settings_are_scoped_per_agency(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        // Change a setting in agency A.
        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'currency', 'value' => 'USD', 'type' => 'string', 'group' => 'general'],
            ],
        ], $this->bearer($tokenA))->assertOk();

        // Agency B admin must still see the default, not A's value.
        $tokenB = $this->loginAs('admin@secondagency.local');
        $response = $this->getJson('/api/v1/settings', $this->bearer($tokenB))->assertOk();

        $this->assertEquals('PKR', $response->json('data.general.currency'));
    }

    public function test_super_admin_has_no_agency_settings(): void
    {
        $token = $this->loginAs('super@aprms.local');

        $this->getJson('/api/v1/settings', $this->bearer($token))
            ->assertStatus(422);
    }
}
