<?php

namespace Tests\Feature;

use App\Domains\Maintenance\Models\MaintenanceTicket;
use App\Domains\Property\Models\Property;
use Tests\TestCase;

class MaintenanceSecurityTest extends TestCase
{
    public function test_maintenance_agency_isolation(): void
    {
        $ticket = MaintenanceTicket::firstOrFail();
        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/maintenance/tickets/{$ticket->id}", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/maintenance/tickets', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($ticket->id, $ids);

        $this->getJson('/api/v1/maintenance/vendors', $this->bearer($tokenB))->assertOk();
        $vendorNames = collect($this->getJson('/api/v1/maintenance/vendors', $this->bearer($tokenB))->json('data'))->pluck('name')->all();
        $this->assertNotContains('Demo Fix-It Services', $vendorNames);
    }

    public function test_technician_assignment_scope(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');

        // Technician sees only assigned tickets.
        $tickets = $this->getJson('/api/v1/maintenance/tickets', $this->bearer($token))->assertOk()->json('data');
        $tech = \App\Domains\Shared\Models\User::where('email', 'technician@ahmedestates.local')->firstOrFail();
        foreach ($tickets as $t) {
            $this->assertEquals($tech->name, $t['assigned_to']['name']);
        }

        // Cannot open an unassigned ticket.
        $unassigned = MaintenanceTicket::whereNull('assigned_to')->first();
        if ($unassigned) {
            $this->getJson("/api/v1/maintenance/tickets/{$unassigned->id}", $this->bearer($token))->assertNotFound();
        }
    }

    public function test_tenant_self_scope(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');
        $property = Property::firstOrFail();

        // Tenant can report.
        $t = $this->postJson('/api/v1/maintenance/tickets', [
            'property_id' => $property->id,
            'category' => 'general',
            'description' => 'Test: tenant self-scope report.',
        ], $this->bearer($token))->assertCreated()->json('data');

        // Tenant sees own ticket.
        $this->getJson("/api/v1/maintenance/tickets/{$t['id']}", $this->bearer($token))->assertOk();

        // Tenant cannot triage/assign.
        $this->postJson("/api/v1/maintenance/tickets/{$t['id']}/transition", ['to' => 'triaged'], $this->bearer($token))
            ->assertForbidden();

        // Tenant cannot approve quotes.
        $this->postJson('/api/v1/maintenance/quotes/1/decide', ['decision' => 'approved'], $this->bearer($token))
            ->assertForbidden();
    }

    public function test_owner_scope(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');

        $tickets = $this->getJson('/api/v1/maintenance/tickets', $this->bearer($token))->assertOk()->json('data');
        // Owner sees tickets for owned properties (may be empty or not, but no 403).
        $this->assertIsArray($tickets);

        // Owner cannot create vendors.
        $this->postJson('/api/v1/maintenance/vendors', ['name' => 'Test'], $this->bearer($token))->assertForbidden();
    }

    public function test_auditor_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $ticket = MaintenanceTicket::firstOrFail();

        $this->getJson('/api/v1/maintenance/tickets', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/maintenance/tickets/{$ticket->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/maintenance/dashboard', $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/maintenance/tickets', [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/maintenance/tickets/{$ticket->id}/transition", ['to' => 'triaged'], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/maintenance/vendors', [], $this->bearer($token))->assertForbidden();
    }

    public function test_assignment_authorization(): void
    {
        // Technician cannot assign tickets (needs maintenance.triage).
        $token = $this->loginAs('technician@ahmedestates.local');
        $ticket = MaintenanceTicket::where('assigned_to', '!=', null)->firstOrFail();

        $this->postJson("/api/v1/maintenance/tickets/{$ticket->id}/assign", [
            'technician_id' => 1,
        ], $this->bearer($token))->assertForbidden();

        // Supervisor can assign.
        $supToken = $this->loginAs('supervisor@ahmedestates.local');
        $open = MaintenanceTicket::where('status', 'open')->first();
        if ($open) {
            $tech = \App\Domains\Shared\Models\User::where('email', 'technician@ahmedestates.local')->firstOrFail();
            $this->postJson("/api/v1/maintenance/tickets/{$open->id}/assign", [
                'technician_id' => $tech->id,
            ], $this->bearer($supToken))->assertOk();
        }
    }

    public function test_vendor_scope(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $vendor = $this->postJson('/api/v1/maintenance/vendors', [
            'name' => 'Scope Test Vendor',
            'category' => 'plumbing',
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('Scope Test Vendor', $vendor['name']);
    }
}
