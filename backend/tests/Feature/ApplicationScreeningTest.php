<?php

namespace Tests\Feature;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Leasing\Models\TenantApplication;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use Tests\TestCase;

class ApplicationScreeningTest extends TestCase
{
    private function makeTenant(string $first = 'App', string $last = 'Test'): Tenant
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $res = $this->postJson('/api/v1/tenants', [
            'first_name' => $first, 'last_name' => $last, 'city' => 'Karachi',
        ], $this->bearer($token))->assertCreated();

        return Tenant::findOrFail($res->json('data.id'));
    }

    public function test_application_lifecycle(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant();
        $property = Property::where('name', 'Bahria Greens Estate')->firstOrFail();
        $unit = Unit::where('unit_number', 'R-102')->firstOrFail();

        // Create (draft)
        $create = $this->postJson('/api/v1/applications', [
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
        ], $this->bearer($token))->assertCreated();
        $id = $create->json('data.id');
        $this->assertEquals('draft', $create->json('data.status'));

        // draft -> submitted -> under_review
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($token))
            ->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'under_review'], $this->bearer($token))
            ->assertOk()->assertJsonPath('data.status', 'under_review');

        // Invalid jump: under_review -> approved (must go through screening)
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'approved'], $this->bearer($token))
            ->assertStatus(422);

        // Start screening
        $this->postJson("/api/v1/applications/{$id}/screening/start", [], $this->bearer($token))
            ->assertOk()->assertJsonPath('data.screening_status', 'in_progress');

        // Approval without clear screening is blocked
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'approved'], $this->bearer($token))
            ->assertStatus(422);

        // Clear screening, then approve
        $this->postJson("/api/v1/applications/{$id}/screening/decide", [
            'clear' => true, 'notes' => 'All checks passed.', 'kyc_status' => 'verified',
        ], $this->bearer($token))->assertOk()->assertJsonPath('data.screening_status', 'clear');

        $this->postJson("/api/v1/applications/{$id}/transition", [
            'status' => 'approved', 'decision_notes' => 'Approved.',
        ], $this->bearer($token))->assertOk()->assertJsonPath('data.status', 'approved');

        // Terminal state: no further transitions
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'rejected'], $this->bearer($token))
            ->assertStatus(422);
    }

    public function test_application_rejection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Reject', 'Me');
        $property = Property::where('name', 'Bahria Greens Estate')->firstOrFail();

        $id = $this->postJson('/api/v1/applications', [
            'tenant_id' => $tenant->id, 'property_id' => $property->id,
        ], $this->bearer($token))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/applications/{$id}/transition", [
            'status' => 'rejected', 'decision_notes' => 'Incomplete documents.',
        ], $this->bearer($token))->assertOk()->assertJsonPath('data.status', 'rejected');

        $app = TenantApplication::findOrFail($id);
        $this->assertNotNull($app->reviewed_at);
        $this->assertEquals('Incomplete documents.', $app->decision_notes);
    }

    public function test_application_rejects_cross_agency_unit(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Cross', 'Agency');
        $property = Property::where('name', 'Gulshan Residency')->firstOrFail();
        $unitB = Unit::withoutAgencyScope()->where('unit_number', 'V-101')->firstOrFail();

        // Unit from agency B with agency A property => 422, no leak.
        $this->postJson('/api/v1/applications', [
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'unit_id' => $unitB->id,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_screening_authorization(): void
    {
        $adminToken = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Screen', 'Auth');
        $property = Property::where('name', 'Bahria Greens Estate')->firstOrFail();

        $id = $this->postJson('/api/v1/applications', [
            'tenant_id' => $tenant->id, 'property_id' => $property->id,
        ], $this->bearer($adminToken))->assertCreated()->json('data.id');
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($adminToken))->assertOk();
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'under_review'], $this->bearer($adminToken))->assertOk();

        // Auditor cannot run screening or transitions.
        $auditorToken = $this->loginAs('auditor@ahmedestates.local');
        $this->postJson("/api/v1/applications/{$id}/screening/start", [], $this->bearer($auditorToken))->assertForbidden();
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($auditorToken))->assertForbidden();

        // Tenant cannot review applications.
        $tenantToken = $this->loginAs('tenant@ahmedestates.local');
        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($tenantToken))->assertForbidden();
    }

    public function test_application_transitions_are_audited(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $tenant = $this->makeTenant('Audit', 'Trail');
        $property = Property::where('name', 'Bahria Greens Estate')->firstOrFail();

        $id = $this->postJson('/api/v1/applications', [
            'tenant_id' => $tenant->id, 'property_id' => $property->id,
        ], $this->bearer($token))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/applications/{$id}/transition", ['status' => 'submitted'], $this->bearer($token))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'applications.submitted',
            'entity_type' => TenantApplication::class,
            'entity_id' => $id,
        ]);
    }
}
