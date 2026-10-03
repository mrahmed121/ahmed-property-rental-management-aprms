<?php

namespace Tests\Feature;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Unit;
use Tests\TestCase;

class LeaseLifecycleTest extends TestCase
{
    private int $counter = 0;

    private function makeTenant(string $tag): Tenant
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $this->counter++;
        $res = $this->postJson('/api/v1/tenants', [
            'first_name' => "Lease{$tag}",
            'last_name' => "Tenant{$this->counter}",
            'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();

        return Tenant::findOrFail($res->json('data.id'));
    }

    private function makeUnit(string $number, string $status = 'vacant'): Unit
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Build a throwaway unit under Block A for lifecycle tests.
        $building = \App\Domains\Property\Models\Building::where('name', 'Block A')->firstOrFail();
        $res = $this->postJson('/api/v1/units', [
            'building_id' => $building->id,
            'unit_number' => $number,
            'unit_type' => 'apartment',
            'status' => $status,
        ], $this->bearer($token))->assertCreated();

        return Unit::findOrFail($res->json('data.id'));
    }

    private function draftLease(string $token, Tenant $tenant, Unit $unit, string $start, string $end): array
    {
        return $this->postJson('/api/v1/leases', [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => $start,
            'end_date' => $end,
            'monthly_rent' => 50000,
            'deposit_amount' => 100000,
        ], $this->bearer($token))->assertCreated()->json('data');
    }

    public function test_lease_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Create');
        $unit = $this->makeUnit('L-101');

        $data = $this->draftLease($token, $tenant, $unit, '2026-11-01', '2027-10-31');

        $this->assertEquals('draft', $data['status']);
        $this->assertMatchesRegularExpression('/^LSE-\d+-2026-\d{6}$/', $data['lease_number']);
        $this->assertEquals($unit->property_id, $data['property']['id']);

        // Draft does not occupy the unit.
        $this->assertEquals('vacant', $unit->fresh()->status);
    }

    public function test_invalid_lease_dates_rejected(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Dates');
        $unit = $this->makeUnit('L-102');

        // End before start.
        $this->postJson('/api/v1/leases', [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => '2027-10-31',
            'end_date' => '2026-11-01',
            'monthly_rent' => 50000,
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_successful_lease_activation_and_occupancy(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Activate');
        $unit = $this->makeUnit('L-103');

        $lease = $this->draftLease($token, $tenant, $unit, '2026-11-01', '2027-10-31');

        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        // Unit becomes occupied, tenant becomes active.
        $this->assertEquals('occupied', $unit->fresh()->status);
        $this->assertEquals('active', $tenant->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leases.activate',
            'entity_id' => $lease['id'],
        ]);
    }

    public function test_overlapping_active_leases_rejected(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant1 = $this->makeTenant('Overlap1');
        $tenant2 = $this->makeTenant('Overlap2');
        $unit = $this->makeUnit('L-104');

        $lease1 = $this->draftLease($token, $tenant1, $unit, '2026-11-01', '2027-10-31');
        $this->postJson("/api/v1/leases/{$lease1['id']}/activate", [], $this->bearer($token))->assertOk();

        // Overlapping second lease must fail at activation.
        $lease2 = $this->draftLease($token, $tenant2, $unit, '2027-06-01', '2028-05-31');
        $this->postJson("/api/v1/leases/{$lease2['id']}/activate", [], $this->bearer($token))
            ->assertStatus(422);

        // Non-overlapping future lease is fine.
        $lease3 = $this->draftLease($token, $tenant2, $unit, '2027-11-01', '2028-10-31');
        $this->assertEquals('draft', $lease3['status']);
    }

    public function test_concurrent_activation_protection(): void
    {
        // Two drafts for the same unit: the second activation must fail
        // because the unit row is locked and the overlap check runs inside
        // the same transaction. Sequential here proves the guard; the
        // lockForUpdate serializes true-parallel attempts.
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant1 = $this->makeTenant('Race1');
        $tenant2 = $this->makeTenant('Race2');
        $unit = $this->makeUnit('L-105');

        $lease1 = $this->draftLease($token, $tenant1, $unit, '2026-11-01', '2027-10-31');
        $lease2 = $this->draftLease($token, $tenant2, $unit, '2026-11-01', '2027-10-31');

        $this->postJson("/api/v1/leases/{$lease1['id']}/activate", [], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/leases/{$lease2['id']}/activate", [], $this->bearer($token))->assertStatus(422);

        // Exactly one active lease exists for the unit.
        $this->assertEquals(1, Lease::where('unit_id', $unit->id)->where('status', 'active')->count());
    }

    public function test_activation_blocked_for_bad_unit_states(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('BadUnit');

        foreach (['maintenance', 'inactive'] as $status) {
            $unit = $this->makeUnit('L-106-'.$status, $status);
            $lease = $this->draftLease($token, $tenant, $unit, '2026-11-01', '2027-10-31');
            $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))
                ->assertStatus(422);
        }
    }

    public function test_renewal_preserves_history(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Renew');
        $unit = $this->makeUnit('L-107');

        $lease = $this->draftLease($token, $tenant, $unit, '2026-01-01', '2026-12-31');
        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();

        // Renew: creates a successor draft, original untouched.
        $renewal = $this->postJson("/api/v1/leases/{$lease['id']}/renew", [
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'monthly_rent' => 55000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('draft', $renewal['status']);
        $this->assertEquals('active', Lease::findOrFail($lease['id'])->status); // original still active

        // Activating the successor flips the original to renewed (history preserved).
        $this->postJson("/api/v1/leases/{$renewal['id']}/activate", [], $this->bearer($token))->assertOk();
        $this->assertEquals('renewed', Lease::findOrFail($lease['id'])->status);
        $this->assertEquals('active', Lease::findOrFail($renewal['id'])->status);

        // Renewal chain is queryable.
        $detail = $this->getJson("/api/v1/leases/{$renewal['id']}", $this->bearer($token))->json('data');
        $this->assertEquals($lease['id'], $detail['previous_lease']['id']);
    }

    public function test_termination_and_vacancy_restoration(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Terminate');
        $unit = $this->makeUnit('L-108');

        $lease = $this->draftLease($token, $tenant, $unit, '2026-01-01', '2026-12-31');
        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();
        $this->assertEquals('occupied', $unit->fresh()->status);

        // Termination requires a reason.
        $this->postJson("/api/v1/leases/{$lease['id']}/terminate", [
            'termination_date' => '2026-06-15',
        ], $this->bearer($token))->assertStatus(422);

        $this->postJson("/api/v1/leases/{$lease['id']}/terminate", [
            'termination_date' => '2026-06-15',
            'reason' => 'Tenant relocated.',
        ], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.status', 'terminated');

        // History preserved, vacancy restored.
        $terminated = Lease::findOrFail($lease['id']);
        $this->assertEquals('Tenant relocated.', $terminated->termination_reason);
        $this->assertNotNull($terminated->terminated_at);
        $this->assertEquals('vacant', $unit->fresh()->status);
        $this->assertEquals('inactive', $tenant->fresh()->status);

        // Cannot terminate twice.
        $this->postJson("/api/v1/leases/{$lease['id']}/terminate", [
            'termination_date' => '2026-07-01',
            'reason' => 'Again.',
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_move_out_inspection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Inspect');
        $unit = $this->makeUnit('L-109');

        $lease = $this->draftLease($token, $tenant, $unit, '2026-01-01', '2026-12-31');
        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();

        // Inspection requires a terminated/expired lease.
        $this->postJson('/api/v1/inspections', [
            'lease_id' => $lease['id'],
            'inspection_date' => '2026-06-16',
            'condition' => 'good',
        ], $this->bearer($token))->assertStatus(422);

        $this->postJson("/api/v1/leases/{$lease['id']}/terminate", [
            'termination_date' => '2026-06-15',
            'reason' => 'End of term.',
        ], $this->bearer($token))->assertOk();

        $inspection = $this->postJson('/api/v1/inspections', [
            'lease_id' => $lease['id'],
            'inspection_date' => '2026-06-16',
            'condition' => 'fair',
            'notes' => 'Minor wear.',
            'damage_observations' => 'Carpet stain in bedroom.',
        ], $this->bearer($token))->assertCreated()->json('data');

        // One inspection per lease.
        $this->postJson('/api/v1/inspections', [
            'lease_id' => $lease['id'],
            'inspection_date' => '2026-06-17',
            'condition' => 'good',
        ], $this->bearer($token))->assertStatus(422);

        // Review workflow.
        $this->postJson("/api/v1/inspections/{$inspection['id']}/review", [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.review_status', 'reviewed');
    }

    public function test_lease_expiry_command(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Expire');
        $unit = $this->makeUnit('L-110');

        $lease = $this->draftLease($token, $tenant, $unit, '2025-01-01', '2025-12-31');
        $this->postJson("/api/v1/leases/{$lease['id']}/activate", [], $this->bearer($token))->assertOk();

        $this->artisan('leases:mark-expired')->assertOk();

        $this->assertEquals('expired', Lease::findOrFail($lease['id'])->status);
        $this->assertEquals('vacant', $unit->fresh()->status);
    }

    public function test_archiving_property_with_active_lease_is_blocked(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = \App\Domains\Property\Models\Property::where('name', 'Bahria Greens Estate')->firstOrFail();

        // Bahria has active leases (seeded) → archive must be blocked.
        $this->deleteJson("/api/v1/properties/{$property->id}", [], $this->bearer($token))
            ->assertStatus(422);
        $this->assertFalse($property->fresh()->trashed());
    }
}
