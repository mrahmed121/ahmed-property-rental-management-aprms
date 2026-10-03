<?php

namespace Tests\Feature;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Property\Models\Property;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    public function test_expense_creation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $res = $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'repairs',
            'description' => 'Test: fix lobby door.',
            'expense_date' => '2026-09-20',
            'amount' => 15000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->assertEquals('draft', $res['status']);
        $this->assertEquals(15000, $res['amount']);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{6}$/', $res['expense_number']);
    }

    public function test_expense_validation(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        // Invalid amount.
        $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'repairs',
            'description' => 'Test invalid.',
            'expense_date' => '2026-09-20',
            'amount' => -100,
        ], $this->bearer($token))->assertStatus(422);

        // Missing category.
        $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'description' => 'Test invalid.',
            'expense_date' => '2026-09-20',
            'amount' => 100,
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_expense_workflow(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $exp = $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'cleaning',
            'description' => 'Test: workflow expense.',
            'expense_date' => '2026-09-20',
            'amount' => 5000,
        ], $this->bearer($token))->assertCreated()->json('data');

        // draft → submitted
        $exp = $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'submitted'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('submitted', $exp['status']);

        // submitted → approved (admin can approve own)
        $exp = $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'approved'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('approved', $exp['status']);

        // approved → posted
        $exp = $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'posted'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('posted', $exp['status']);

        // posted is immutable (cannot go back to draft).
        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'draft'], $this->bearer($token))
            ->assertStatus(422);
    }

    public function test_expense_rejection(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $exp = $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'supplies',
            'description' => 'Test: rejection flow.',
            'expense_date' => '2026-09-20',
            'amount' => 2000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'submitted'], $this->bearer($token))->assertOk();

        $exp = $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'rejected'], $this->bearer($token))
            ->assertOk()->json('data');
        $this->assertEquals('rejected', $exp['status']);
    }

    public function test_expense_approval_permissions(): void
    {
        $adminToken = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $exp = $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'repairs',
            'description' => 'Test: approval permission.',
            'expense_date' => '2026-09-20',
            'amount' => 3000,
        ], $this->bearer($adminToken))->assertCreated()->json('data');

        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'submitted'], $this->bearer($adminToken))->assertOk();

        // Manager (no expenses.approve) cannot approve.
        $mgrToken = $this->loginAs('manager@ahmedestates.local');
        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'approved'], $this->bearer($mgrToken))
            ->assertForbidden();
    }

    public function test_expense_reversal(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        $property = Property::firstOrFail();

        $exp = $this->postJson('/api/v1/expenses', [
            'property_id' => $property->id,
            'category' => 'security',
            'description' => 'Test: reversal.',
            'expense_date' => '2026-09-20',
            'amount' => 7000,
        ], $this->bearer($token))->assertCreated()->json('data');

        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'submitted'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'approved'], $this->bearer($token))->assertOk();
        $this->postJson("/api/v1/expenses/{$exp['id']}/transition", ['to' => 'posted'], $this->bearer($token))->assertOk();

        $reversed = $this->postJson("/api/v1/expenses/{$exp['id']}/reverse", [
            'reason' => 'Test reversal.',
        ], $this->bearer($token))->assertOk()->json('data');

        $this->assertEquals('reversed', $reversed['status']);

        // Not hard-deleted.
        $this->assertDatabaseHas('expenses', ['id' => $exp['id'], 'status' => 'reversed']);
    }

    public function test_expense_summary(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $res = $this->getJson('/api/v1/expenses/summary', $this->bearer($token))
            ->assertOk()->json('data');

        $this->assertArrayHasKey('total_expenses', $res);
        $this->assertArrayHasKey('by_category', $res);
        $this->assertArrayHasKey('by_property', $res);
        $this->assertIsNumeric($res['total_expenses']);
    }

    public function test_expense_does_not_touch_tenant_ledger(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');
        // Seeded posted expense — verify no tenant ledger entries reference it.
        $expense = Expense::where('status', 'posted')->firstOrFail();

        $count = \App\Domains\Billing\Models\TenantLedgerEntry::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)->count();

        $this->assertEquals(0, $count, 'Expenses must never post to the tenant ledger.');
    }
}
