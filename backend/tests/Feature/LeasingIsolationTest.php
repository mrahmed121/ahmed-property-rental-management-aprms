<?php

namespace Tests\Feature;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeasingIsolationTest extends TestCase
{
    public function test_tenant_portal_isolation(): void
    {
        // tenant@ahmedestates.local is linked to Ahmed Raza (seeded).
        $token = $this->loginAs('tenant@ahmedestates.local');

        // Sees only their own tenant record.
        $tenants = $this->getJson('/api/v1/tenants', $this->bearer($token))->assertOk()->json('data');
        $this->assertCount(1, $tenants);
        $this->assertEquals('Ahmed Raza', $tenants[0]['name']);

        // Sees only their own leases.
        $leases = $this->getJson('/api/v1/leases', $this->bearer($token))->assertOk()->json('data');
        foreach ($leases as $l) {
            $this->assertEquals('Ahmed Raza', $l['tenant']['name']);
        }
        $this->assertNotEmpty($leases);

        // Cannot open another tenant's lease.
        $otherLease = Lease::whereHas('tenant', fn ($q) => $q->where('first_name', 'Fatima'))->firstOrFail();
        $this->getJson("/api/v1/leases/{$otherLease->id}", $this->bearer($token))->assertNotFound();

        // Cannot create tenants or leases.
        $this->postJson('/api/v1/tenants', ['first_name' => 'x', 'last_name' => 'y'], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/leases', [], $this->bearer($token))->assertForbidden();
    }

    public function test_owner_portfolio_isolation(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');

        // Owner owns Gulshan + Bahria Greens. Seeded leases are on Bahria units.
        $tenants = $this->getJson('/api/v1/tenants', $this->bearer($token))->assertOk()->json('data');
        $names = collect($tenants)->pluck('name')->all();
        $this->assertContains('Ahmed Raza', $names);
        $this->assertContains('Sara Malik', $names); // terminated lease still visible (history)

        $leases = $this->getJson('/api/v1/leases', $this->bearer($token))->assertOk()->json('data');
        foreach ($leases as $l) {
            $this->assertContains($l['property']['name'], ['Gulshan Residency', 'Bahria Greens Estate']);
        }

        // Owner cannot manage leases.
        $leaseId = $leases[0]['id'];
        $this->postJson("/api/v1/leases/{$leaseId}/terminate", [
            'termination_date' => now()->toDateString(), 'reason' => 'x',
        ], $this->bearer($token))->assertForbidden();
    }

    public function test_cross_agency_lease_isolation(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');

        // Plant a lease in agency B.
        $agencyB = \App\Domains\Shared\Models\Agency::where('slug', 'second-agency')->firstOrFail();
        $tenantB = Tenant::create(['agency_id' => $agencyB->id, 'first_name' => 'Second', 'last_name' => 'Lease']);
        $unitB = \App\Domains\Property\Models\Unit::withoutAgencyScope()->where('unit_number', 'V-101')->firstOrFail();
        $leaseB = Lease::create([
            'agency_id' => $agencyB->id, 'tenant_id' => $tenantB->id, 'unit_id' => $unitB->id,
            'property_id' => $unitB->property_id, 'building_id' => $unitB->building_id,
            'lease_number' => 'LSE-B-TEST', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'monthly_rent' => 1000, 'status' => 'draft',
        ]);

        $this->getJson("/api/v1/leases/{$leaseB->id}", $this->bearer($tokenA))->assertNotFound();
        $this->postJson("/api/v1/leases/{$leaseB->id}/activate", [], $this->bearer($tokenA))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/leases', $this->bearer($tokenA))->json('data'))->pluck('id')->all();
        $this->assertNotContains($leaseB->id, $ids);

        $leaseB->forceDelete();
        $tenantB->forceDelete();
    }

    public function test_auditor_is_read_only_on_leasing(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $lease = Lease::firstOrFail();

        $this->getJson('/api/v1/tenants', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/applications', $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/leases', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/leases/{$lease->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/inspections', $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/tenants', ['first_name' => 'x', 'last_name' => 'y'], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/leases', [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/leases/{$lease->id}/activate", [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/leases/{$lease->id}/terminate", [], $this->bearer($token))->assertForbidden();
        $this->postJson('/api/v1/inspections', [], $this->bearer($token))->assertForbidden();
    }

    public function test_lease_document_scoping(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $lease = Lease::where('status', 'active')->firstOrFail();

        // Upload a document to the lease.
        $file = UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf');
        $upload = $this->call(
            'POST', '/api/v1/documents',
            ['parent_type' => 'lease', 'parent_id' => $lease->id, 'document_type' => 'agreement'],
            [], ['file' => $file],
            $this->transformHeadersToServerVars($this->bearer($tokenA) + ['Accept' => 'application/json'])
        )->assertStatus(201);
        $docId = $upload->json('data.id');

        // Agency B cannot see or download it.
        $tokenB = $this->loginAs('admin@secondagency.local');
        $docsB = $this->getJson('/api/v1/documents', $this->bearer($tokenB))->json('data');
        $this->assertNotContains($docId, collect($docsB)->pluck('id')->all());
        $this->call(
            'GET', "/api/v1/documents/{$docId}/download", [], [], [],
            $this->transformHeadersToServerVars($this->bearer($tokenB) + ['Accept' => 'application/json'])
        )->assertStatus(404);

        // Tenant sees only their own lease's documents.
        $tokenT = $this->loginAs('tenant@ahmedestates.local');
        $docsT = $this->getJson('/api/v1/documents', $this->bearer($tokenT))->assertOk()->json('data');
        foreach ($docsT as $d) {
            $this->assertEquals('lease', $d['parent_type']);
        }
    }

    public function test_dashboard_leasing_metrics(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $data = $this->getJson('/api/v1/dashboard/stats', $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals(5, $data['total_tenants']);
        $this->assertEquals(2, $data['active_leases']);
        $this->assertEquals(1, $data['leases_expiring_soon']); // Fatima's lease ends in 20 days
        $this->assertArrayHasKey('leases_by_status', $data);
    }

    public function test_tenant_dashboard_is_scoped(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        $data = $this->getJson('/api/v1/dashboard/stats', $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals(1, $data['total_tenants']);
        $this->assertEquals(1, $data['active_leases']);
    }
}
