<?php

namespace Tests\Feature;

use App\Domains\Deposits\Models\Deposit;
use App\Domains\Maintenance\Models\MaintenanceTicket;
use Tests\TestCase;

class DepositSecurityTest extends TestCase
{
    public function test_deposit_agency_isolation(): void
    {
        $tokenA = $this->loginAs('admin@ahmedestates.local');
        $deposit = Deposit::firstOrFail();

        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/deposits/{$deposit->id}", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/deposits', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($deposit->id, $ids);

        $this->postJson("/api/v1/deposits/{$deposit->id}/settlement/preview", [], $this->bearer($tokenB))->assertNotFound();
    }

    public function test_deposit_tenant_scope(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local'); // Ahmed Raza
        $own = Deposit::whereHas('tenant', fn ($q) => $q->where('first_name', 'Ahmed'))->firstOrFail();
        $other = Deposit::whereHas('tenant', fn ($q) => $q->where('first_name', 'Fatima'))->firstOrFail();

        $this->getJson("/api/v1/deposits/{$own->id}", $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/deposits/{$other->id}", $this->bearer($token))->assertNotFound();

        // Tenant cannot create deposits.
        $this->postJson('/api/v1/deposits', [], $this->bearer($token))->assertForbidden();
        // Tenant cannot settle.
        $this->postJson("/api/v1/deposits/{$own->id}/settlement/preview", [], $this->bearer($token))->assertForbidden();
    }

    public function test_deposit_owner_scope(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');

        $deposits = $this->getJson('/api/v1/deposits', $this->bearer($token))->assertOk()->json('data');
        $this->assertNotEmpty($deposits);

        // Owner cannot manage deposits.
        $this->postJson('/api/v1/deposits', [], $this->bearer($token))->assertForbidden();
    }

    public function test_deposit_auditor_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $deposit = Deposit::firstOrFail();

        $this->getJson('/api/v1/deposits', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/deposits/{$deposit->id}", $this->bearer($token))->assertOk();

        $this->postJson('/api/v1/deposits', [], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/deposits/{$deposit->id}/receive", ['amount' => 100], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/deposits/{$deposit->id}/settlement/finalize", [], $this->bearer($token))->assertForbidden();
    }

    public function test_deposit_technician_denied(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');
        $deposit = Deposit::firstOrFail();

        // Technician has no deposit permissions at all.
        $this->getJson('/api/v1/deposits', $this->bearer($token))->assertForbidden();
        $this->getJson("/api/v1/deposits/{$deposit->id}", $this->bearer($token))->assertForbidden();
    }

    public function test_deposit_concurrency_no_double_settlement(): void
    {
        // UNIQUE(agency_id, deposit_id) on settlements prevents duplicates
        // even if two finalize calls race. Tested via idempotent finalize.
        $token = $this->loginAs('admin@ahmedestates.local');
        $deposit = Deposit::where('status', 'settled')->first();
        if (! $deposit) $this->markTestSkipped('No settled deposit seeded.');

        $settlement = $deposit->settlement;
        $this->postJson("/api/v1/deposits/{$deposit->id}/settlement/finalize", [], $this->bearer($token))->assertOk();
        $this->assertEquals(1, \App\Domains\Deposits\Models\DepositSettlement::where('deposit_id', $deposit->id)->count());
    }
}
