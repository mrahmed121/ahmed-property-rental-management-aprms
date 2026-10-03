<?php

namespace Tests\Feature;

use App\Domains\Shared\Models\User;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementPeriod;
use Tests\TestCase;

class P7SecurityTest extends TestCase
{
    public function test_statement_agency_isolation(): void
    {
        $stmt = OwnerStatement::firstOrFail();
        $tokenB = $this->loginAs('admin@secondagency.local');

        $this->getJson("/api/v1/owner-statements/{$stmt->id}", $this->bearer($tokenB))->assertNotFound();

        $ids = collect($this->getJson('/api/v1/owner-statements', $this->bearer($tokenB))->json('data'))->pluck('id')->all();
        $this->assertNotContains($stmt->id, $ids);
    }

    public function test_owner_portal_scope(): void
    {
        $token = $this->loginAs('owner@ahmedestates.local');
        $owner = User::where('email', 'owner@ahmedestates.local')->firstOrFail();

        $statements = $this->getJson('/api/v1/owner-statements', $this->bearer($token))
            ->assertOk()->json('data');

        foreach ($statements as $s) {
            $this->assertEquals($owner->id, $s['owner']['id']);
        }

        // Owner cannot generate (no permission).
        $period = StatementPeriod::firstOrFail();
        $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertForbidden();

        // Owner cannot approve.
        $stmt = OwnerStatement::where('owner_id', $owner->id)->first();
        if ($stmt) {
            $this->postJson("/api/v1/owner-statements/{$stmt->id}/transition", ['to' => 'approved'], $this->bearer($token))
                ->assertForbidden();
        }
    }

    public function test_owner_cannot_see_other_owner_statements(): void
    {
        // Create a second owner with a statement, verify first owner gets 404.
        $token = $this->loginAs('admin@ahmedestates.local');

        $secondOwner = User::where('agency_id', 1)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->where('email', '!=', 'owner@ahmedestates.local')
            ->first();

        if (! $secondOwner) {
            $this->markTestSkipped('No second owner in seed data.');
        }

        $period = StatementPeriod::firstOrFail();
        $stmt = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $secondOwner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        $ownerToken = $this->loginAs('owner@ahmedestates.local');
        $this->getJson("/api/v1/owner-statements/{$stmt['id']}", $this->bearer($ownerToken))->assertNotFound();
    }

    public function test_tenant_no_statement_access(): void
    {
        $token = $this->loginAs('tenant@ahmedestates.local');

        $this->getJson('/api/v1/owner-statements', $this->bearer($token))->assertForbidden();
        $this->getJson('/api/v1/owner-reports/portfolio', $this->bearer($token))->assertForbidden();
    }

    public function test_technician_no_statement_access(): void
    {
        $token = $this->loginAs('technician@ahmedestates.local');

        $this->getJson('/api/v1/owner-statements', $this->bearer($token))->assertForbidden();
    }

    public function test_p7_auditor_read_only(): void
    {
        $token = $this->loginAs('auditor@ahmedestates.local');
        $stmt = OwnerStatement::firstOrFail();

        $this->getJson('/api/v1/owner-statements', $this->bearer($token))->assertOk();
        $this->getJson("/api/v1/owner-statements/{$stmt->id}", $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/owner-reports/portfolio?owner_id='.$stmt->owner_id, $this->bearer($token))->assertOk();

        $period = StatementPeriod::firstOrFail();
        $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $stmt->owner_id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/owner-statements/{$stmt->id}/transition", ['to' => 'approved'], $this->bearer($token))->assertForbidden();
        $this->postJson("/api/v1/owner-statements/{$stmt->id}/adjust", ['amount' => 100, 'reason' => 'test test'], $this->bearer($token))->assertForbidden();
    }

    public function test_approval_authorization(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = StatementPeriod::create([
            'agency_id' => 1,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'open',
        ]);
        $owner = User::where('email', 'owner@ahmedestates.local')->firstOrFail();

        $stmt = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        // Manager (no statements.approve) cannot approve.
        $mgrToken = $this->loginAs('manager@ahmedestates.local');
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'review'], $this->bearer($mgrToken))
            ->assertForbidden();
    }

    public function test_owner_reports(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $owner = User::where('email', 'owner@ahmedestates.local')->firstOrFail();

        $portfolio = $this->getJson("/api/v1/owner-reports/portfolio?owner_id={$owner->id}", $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertArrayHasKey('properties', $portfolio);
        $this->assertArrayHasKey('statements', $portfolio);

        $profit = $this->getJson("/api/v1/owner-reports/profitability?owner_id={$owner->id}", $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertArrayHasKey('properties', $profit);

        $trend = $this->getJson("/api/v1/owner-reports/trend?owner_id={$owner->id}", $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertArrayHasKey('trend', $trend);
    }
}
