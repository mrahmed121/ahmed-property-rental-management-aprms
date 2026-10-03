<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Maintenance\Models\MaintenanceVendor;
use App\Domains\Property\Models\Property;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * ExpenseService — property expense workflow.
 *
 * DRAFT → SUBMITTED → APPROVED/REJECTED → POSTED → (REVERSED).
 * Posted expenses are immutable; corrections use reversal.
 * Expenses are money-out on the property/owner side — they NEVER
 * touch the tenant rent ledger.
 */
class ExpenseService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Expense::with([
            'property:id,name', 'vendor:id,name', 'submittedBy:id,name', 'approvedBy:id,name',
        ])->orderByDesc('expense_date');

        $this->applyScope($query);

        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);
        if (! empty($filters['category'])) $query->where('category', $filters['category']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['from'])) $query->whereDate('expense_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('expense_date', '<=', $filters['to']);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('expense_number', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Expense
    {
        $expense = Expense::with([
            'property', 'building', 'unit', 'vendor',
            'submittedBy:id,name', 'approvedBy:id,name', 'documents',
        ])->findOrFail($id);
        $this->ensureExpenseAccess($expense);

        return $expense;
    }

    public function create(array $data): Expense
    {
        $property = Property::findOrFail($data['property_id']);
        $this->ensureAgencyAccess($property->agency_id);

        if (! in_array($data['category'], Expense::CATEGORIES, true)) {
            abort(422, 'Invalid expense category.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            abort(422, 'Expense amount must be positive.');
        }

        $vendorId = $data['vendor_id'] ?? null;
        if ($vendorId) {
            $vendor = MaintenanceVendor::findOrFail($vendorId);
            if ($vendor->agency_id !== $property->agency_id) {
                abort(422, 'Vendor belongs to a different agency.');
            }
        }

        $expense = Expense::create([
            'agency_id' => $property->agency_id,
            'property_id' => $property->id,
            'building_id' => $data['building_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'vendor_id' => $vendorId,
            'expense_number' => $this->nextExpenseNumber($property->agency_id),
            'category' => $data['category'],
            'description' => $data['description'],
            'expense_date' => $data['expense_date'],
            'amount' => $amount,
            'currency' => 'PKR',
            'status' => 'draft',
            'submitted_by' => $this->actor()?->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit()->log('expenses.create', $expense, [
            'expense_number' => $expense->expense_number,
            'amount' => $amount,
        ]);

        return $expense;
    }

    /** Move along the workflow. */
    public function transition(Expense $expense, string $to): Expense
    {
        $this->ensureExpenseAccess($expense);

        return DB::transaction(function () use ($expense, $to) {
            $expense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            if (! Expense::canTransition($expense->status, $to)) {
                abort(422, "Cannot move expense from {$expense->status} to {$to}.");
            }

            $actor = $this->actor();

            // Segregation of duties: approver cannot be the submitter
            // unless they hold explicit approval rights AND are not the
            // original creator (configurable strictness: enforced here).
            if ($to === 'approved' && (int) $expense->submitted_by === (int) $actor?->id) {
                // Allowed only for agency-admin/super-admin; others blocked.
                if (! $actor->hasRole('agency-admin') && ! $actor->hasRole('super-admin')) {
                    abort(403, 'You cannot approve your own submitted expense.');
                }
            }

            $from = $expense->status;
            $expense->status = $to;

            if ($to === 'submitted') {
                $expense->submitted_by = $actor?->id;
            }
            if (in_array($to, ['approved', 'rejected'], true)) {
                $expense->approved_by = $actor?->id;
                $expense->approved_at = now();
            }
            if ($to === 'posted') {
                $expense->posted_at = now();
            }
            $expense->save();

            $this->audit()->log('expenses.transition', $expense, [
                'from' => $from, 'to' => $to,
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Reverse a posted expense (correction path). Never hard-deleted.
     */
    public function reverse(Expense $expense, string $reason): Expense
    {
        $this->ensureExpenseAccess($expense);

        return DB::transaction(function () use ($expense, $reason) {
            $expense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->status !== 'posted') {
                abort(422, 'Only posted expenses can be reversed.');
            }
            if (trim($reason) === '') {
                abort(422, 'A reason is required.');
            }

            $expense->status = 'reversed';
            $expense->notes = trim(($expense->notes ? $expense->notes."\n" : '')."Reversed: {$reason}");
            $expense->save();

            $this->audit()->log('expenses.reverse', $expense, ['reason' => $reason]);

            return $expense->fresh();
        });
    }

    /** Summary metrics for reporting (query-backed). */
    public function summary(array $filters = []): array
    {
        $base = Expense::query();
        $this->applyScope($base);

        if (! empty($filters['property_id'])) $base->where('property_id', $filters['property_id']);
        if (! empty($filters['from'])) $base->whereDate('expense_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $base->whereDate('expense_date', '<=', $filters['to']);

        // Only posted (and not reversed) count as real spend.
        $posted = (clone $base)->where('expenses.status', 'posted');

        $byCategory = [];
        foreach (Expense::CATEGORIES as $c) {
            $byCategory[$c] = round((float) (clone $posted)->where('expenses.category', $c)->sum('amount'), 2);
        }

        $byProperty = (clone $posted)
            ->join('properties', 'properties.id', '=', 'expenses.property_id')
            ->selectRaw('properties.name as property, SUM(expenses.amount) as total')
            ->groupBy('properties.id', 'properties.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['property' => $r->property, 'total' => round((float) $r->total, 2)])
            ->all();

        return [
            'total_expenses' => round((float) (clone $posted)->sum('amount'), 2),
            'posted_count' => (clone $posted)->count(),
            'pending_approvals' => (clone $base)->where('expenses.status', 'submitted')->count(),
            'draft_count' => (clone $base)->where('expenses.status', 'draft')->count(),
            'by_category' => $byCategory,
            'by_property' => $byProperty,
            'currency' => 'PKR',
        ];
    }

    public function ensureExpenseAccess(Expense $expense): void
    {
        $this->ensureAgencyAccess($expense->agency_id);

        $actor = $this->actor();
        if (! $actor) return;

        // Tenants and technicians have no expense access.
        if ($actor->hasRole('tenant') || $actor->hasRole('technician')) {
            abort(404);
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds) && ! in_array($expense->property_id, $propertyIds, true)) {
                abort(404);
            }
        }
    }

    private function applyScope($query): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('tenant') || $actor->hasRole('technician')) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }
        }
    }

    private function nextExpenseNumber(int $agencyId): string
    {
        $year = now()->format('Y');
        $last = Expense::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('expense_number', 'like', "EXP-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $seq = $last ? ((int) substr($last->expense_number, -6)) + 1 : 1;

        return sprintf('EXP-%s-%06d', $year, $seq);
    }
}
