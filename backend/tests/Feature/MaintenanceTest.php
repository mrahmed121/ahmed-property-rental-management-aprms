<?php

namespace Tests\Feature;

use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Property\Models\Property;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    public function test_ticket_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $res = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'plumbing',
            'description' => 'Test: leaking tap in bathroom.',
            'priority' => 'high',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertMatchesRegularExpression('/^MT-\d{4}-\d{6}$/', $res['ticket_number']);
        $this->assertEquals('open', $res['status']);
        $this->assertEquals('high', $res['priority']);
        $this->assertNotNull($res['sla_due_at']);
        $this->assertFalse($res['breached']);
    }

    public function test_sla_calculation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        // Urgent = 4 hours.
        $res = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'electrical',
            'description' => 'Test: urgent SLA check.',
            'priority' => 'urgent',
        ], $this->bearer($token))->assertCreated()->json('data');

        $ticket = MaintenanceTicket::findOrFail($res['id']);
        $expected = now()->addHours(4);
        $this->assertTrue(
            abs($ticket->sla_due_at->diffInMinutes($expected)) < 5,
            'Urgent SLA should be ~4 hours from creation.'
        );
    }

    public function test_status_transitions(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();
        $tech = \App\Domains\Shared\Models\User::where('email', 'technician@ahmedestates.local')->firstOrFail();

        $t = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'general',
            'description' => 'Test: transition flow.',
        ], $this->bearer($token))->assertCreated()->json('data');

        // open → triaged
        $t = $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'triaged'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('triaged', $t['status']);

        // triaged → assigned (via assign endpoint)
        $t = $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/assign", [
            'technician_id' => $tech->id,
        ], $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('assigned', $t['status']);

        // assigned → quoted (via quote) → approval_pending
        $q = $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/quotes", [
            'provider' => 'Test Tech',
            'labor_cost' => 1000,
            'materials_cost' => 500,
            'attribution' => 'owner',
            'attribution_reason' => 'Test: normal wear.',
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->assertEquals('pending', $q['status']);

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('approval_pending', $t['status']);

        // approval_pending → approved
        $this->postJson("/api/v1/maintenance/quotes/{$q['id']}/decide", [
            'decision' => 'approved',
        ], $this->bearer($token))->assertOk();

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('approved', $t['status']);

        // approved → in_progress (via work log)
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/work-logs", [
            'notes' => 'Test: work started.',
        ], $this->bearer($token))->assertCreated();

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('in_progress', $t['status']);

        // in_progress → completed
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'completed'], $this->bearer($token))->assertOk();

        // completed → verified (via verify)
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/verify", [
            'result' => 'passed',
        ], $this->bearer($token))->assertOk();

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('verified', $t['status']);

        // verified → closed
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'closed'], $this->bearer($token))->assertOk();

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('closed', $t['status']);
    }

    public function test_invalid_status_transition(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $t = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'general',
            'description' => 'Test: invalid transition.',
        ], $this->bearer($token))->assertCreated()->json('data');

        // open → closed is not allowed.
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'closed'], $this->bearer($token))
            ->assertStatus(422);

        // open → verified is not allowed.
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'verified'], $this->bearer($token))
            ->assertStatus(422);
    }

    public function test_quote_requires_attribution_reason(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();
        $tech = \App\Domains\Shared\Models\User::where('email', 'technician@ahmedestates.local')->firstOrFail();

        $t = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'general',
            'description' => 'Test: quote validation.',
        ], $this->bearer($token))->assertCreated()->json('data');
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/assign", [
            'technician_id' => $tech->id,
        ], $this->bearer($token))->assertOk();

        // Missing attribution_reason → 422.
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/quotes", [
            'provider' => 'Test',
            'labor_cost' => 1000,
            'materials_cost' => 500,
            'attribution' => 'tenant',
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_verification_failed_sends_back(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();
        $tech = \App\Domains\Shared\Models\User::where('email', 'technician@ahmedestates.local')->firstOrFail();

        $t = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'general',
            'description' => 'Test: failed verification.',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'triaged'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/assign", ['technician_id' => $tech->id], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'in_progress'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'completed'], $this->bearer($token))->assertOk();

        // Failed verification → back to in_progress.
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/verify", [
            'result' => 'failed',
            'notes' => 'Test: not good enough.',
        ], $this->bearer($token))->assertOk();

        $t = $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk()->json('data');
        $this->assertEquals('in_progress', $t['status']);
    }

    public function test_maintenance_dashboard(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $res = $this->getJson('/api/v1/maintenance/dashboard', $this->bearer($token))
            ->assertOk()->json('data');

        foreach (['open_tickets', 'urgent_tickets', 'sla_breached', 'pending_approvals', 'approved_spend'] as $key) {
            $this->assertArrayHasKey($key, $res);
            $this->assertIsNumeric($res[$key]);
        }

        // Seeded urgent breached ticket is counted.
        $this->assertGreaterThanOrEqual(1, $res['urgent_tickets']);
        $this->assertGreaterThanOrEqual(1, $res['sla_breached']);
    }
}
