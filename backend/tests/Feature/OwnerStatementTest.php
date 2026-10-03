<?php

namespace Tests\Feature;

use App\Domains\Shared\Models\User;
use App\Domains\Statements\Models\OwnerStatement;
use App\Domains\Statements\Models\StatementPeriod;
use Tests\TestCase;

class OwnerStatementTest extends TestCase
{
    private function makePeriod(): StatementPeriod
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $res = $this->postJson('/api/v1/statement-periods', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ], $this->bearer($token))->assertCreated()->json('data');

        return StatementPeriod::findOrFail($res['id']);
    }

    private function ownerUser(): User
    {
        return User::where('email', 'owner@ahmedestates.local')->firstOrFail();
    }

    public function test_statement_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = $this->makePeriod();
        $owner = $this->ownerUser();

        $res = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('draft', $res['status']);
        $this->assertMatchesRegularExpression('/^STMT-\d{4}-\d{6}$/', $res['statement_number']);
        $this->assertArrayHasKey('net_amount', $res);
    }

    public function test_duplicate_statement_prevention(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = $this->makePeriod();
        $owner = $this->ownerUser();

        $first = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        // Second generate returns the same (idempotent).
        $second = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals($first['id'], $second['id']);

        $count = OwnerStatement::where('owner_id', $owner->id)
            ->where('statement_period_id', $period->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_reconciliation_arithmetic(): void
    {
        // Deterministic: net = income − fee − expenses − maintenance − utility + adjustments
        $net = OwnerStatement::reconcile(100000, 10000, 15000, 5000, 2000, 3000);
        $this->assertEquals(71000, $net);

        $net2 = OwnerStatement::reconcile(50000, 5000, 0, 0, 0, -2000);
        $this->assertEquals(43000, $net2);
    }

    public function test_statement_workflow(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = $this->makePeriod();
        $owner = $this->ownerUser();

        $stmt = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        // draft → review
        $stmt = $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'review'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('review', $stmt['status']);

        // review → approved
        $stmt = $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'approved'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('approved', $stmt['status']);

        // approved → finalized
        $stmt = $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'finalized'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('finalized', $stmt['status']);
        $this->assertNotNull($stmt['finalized_at']);

        // finalized is terminal.
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'draft'], $this->bearer($token))
            ->assertStatus(422);
    }

    public function test_adjustment_workflow(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = $this->makePeriod();
        $owner = $this->ownerUser();

        $stmt = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        $before = $stmt['net_amount'];

        $stmt = $this->postJson("/api/v1/owner-statements/{$stmt['id']}/adjust", [
            'amount' => 5000,
            'reason' => 'Test credit adjustment.',
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals($before + 5000, $stmt['net_amount']);
        $this->assertEquals(5000, $stmt['adjustments_total']);

        // Finalize, then adjustment should fail.
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'review'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'approved'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'finalized'], $this->bearer($token))->assertOk();

        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/adjust", [
            'amount' => 100,
            'reason' => 'Should fail.',
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_period_lock(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $period = $this->makePeriod();
        $owner = $this->ownerUser();

        $stmt = $this->postJson('/api/v1/owner-statements', [
            'owner_id' => $owner->id,
            'period_id' => $period->id,
        ], $this->bearer($token))->assertCreated()->json('data');

        // Cannot lock with unfinalized statements.
        $this->postJson("/api/v1/statement-periods/{$period->id}/lock", [], $this->bearer($token))
            ->assertStatus(422);

        // Finalize then lock.
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'review'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'approved'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/owner-statements/{$stmt['id']}/transition", ['to' => 'finalized'], $this->bearer($token))->assertOk();

        $locked = $this->postJson("/api/v1/statement-periods/{$period->id}/lock", [], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('locked', $locked['status']);

        // Locked period blocks new generation.
        $otherOwner = User::where('email', 'owner2@ahmedestates.local')->first();
        if ($otherOwner) {
            $this->postJson('/api/v1/owner-statements', [
                'owner_id' => $otherOwner->id,
                'period_id' => $period->id,
            ], $this->bearer($token))->assertStatus(422);
        }
    }

    public function test_statement_line_traceability(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Seeded September statement has real lines.
        $stmt = OwnerStatement::where('statement_number', 'STMT-2026-000001')->firstOrFail();

        $res = $this->getJson("/api/v1/owner-statements/{$stmt->id}", $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertNotEmpty($res['lines']);
        foreach ($res['lines'] as $line) {
            $this->assertNotEmpty($line['description']);
            $this->assertNotEmpty($line['line_date']);
            $this->assertIsNumeric($line['amount']);
        }

        // Reconciliation holds on the persisted statement.
        $expected = $res['gross_income'] - $res['management_fee'] - $res['owner_expenses']
            - $res['owner_maintenance'] - $res['owner_utility_absorption'] + $res['adjustments_total'];
        $this->assertEquals(round($expected, 2), round($res['net_amount'], 2));
    }

    public function test_pdf_generation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $stmt = OwnerStatement::firstOrFail();

        $response = $this->get("/api/v1/owner-statements/{$stmt->id}/pdf", $this->bearer($token));
        $response->assertOk();
        $this->assertStringContainsString('pdf', $response->headers->get('Content-Type'));
    }
}
