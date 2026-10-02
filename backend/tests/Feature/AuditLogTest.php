<?php

namespace Tests\Feature;

use App\Domains\Shared\Models\AuditLog;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_user_creation_is_audited(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => 'Audited User',
            'email' => 'audited@ahmedestates.local',
            'password' => 'password123',
        ], $this->bearer($token))->assertCreated();

        $entry = AuditLog::where('action', 'users.create')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('App\Domains\Shared\Models\User', $entry->entity_type);
        $this->assertNotNull($entry->entity_id);
        $this->assertNotNull($entry->user_id); // actor recorded
        $this->assertArrayHasKey('ip', $entry->context ?? []);
    }

    public function test_user_update_captures_old_values(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $me = $this->getJson('/api/v1/me', $this->bearer($token))->json('data');

        $this->putJson("/api/v1/users/{$me['id']}", [
            'phone' => '+92-300-0000001',
        ], $this->bearer($token))->assertOk();

        $entry = AuditLog::where('action', 'users.update')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('phone', $entry->old_values ?? []);
        $this->assertEquals('+92-300-0000001', $entry->new_values['phone'] ?? null);
    }

    public function test_audit_log_is_read_only_via_api(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        // The audit-logs URI only serves GET: POST hits 405 (method not
        // allowed on a matched URI); fabricated member URIs hit 404.
        // Either way, there is no write path.
        $this->postJson('/api/v1/audit-logs', [], $this->bearer($token))
            ->assertStatus(405);
        $this->putJson('/api/v1/audit-logs/1', [], $this->bearer($token))
            ->assertStatus(404);
        $this->deleteJson('/api/v1/audit-logs/1', [], $this->bearer($token))
            ->assertStatus(404);
    }

    public function test_audit_logs_are_agency_scoped(): void
    {
        // Agency A generates an audited action.
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $this->postJson('/api/v1/users', [
            'name' => 'Scoped Audit',
            'email' => 'scopedaudit@ahmedestates.local',
            'password' => 'password123',
        ], $this->bearer($tokenA))->assertCreated();

        // Agency B auditor must not see agency A's entries.
        $tokenB = $this->loginAs('admin@secondagency.local');
        $response = $this->getJson('/api/v1/audit-logs', $this->bearer($tokenB))->assertOk();

        $actions = collect($response->json('data'))->pluck('action')->all();
        $this->assertNotContains('users.create', $actions);
    }
}
