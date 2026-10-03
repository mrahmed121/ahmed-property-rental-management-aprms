<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->expenses->list($request->only([
            'property_id', 'category', 'status', 'from', 'to', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($e) => $this->payload($e)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $e = $this->expenses->find($id);
        $data = $this->payload($e);
        $data['building'] = $e->building ? ['id' => $e->building->id, 'name' => $e->building->name] : null;
        $data['unit'] = $e->unit ? ['id' => $e->unit->id, 'unit_number' => $e->unit->unit_number] : null;
        $data['notes'] = $e->notes;
        $data['approved_at'] = $e->approved_at?->toDateTimeString();
        $data['posted_at'] = $e->posted_at?->toDateTimeString();
        $data['documents'] = $e->documents->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name,
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'vendor_id' => 'nullable|integer|exists:maintenance_vendors,id',
            'category' => 'required|in:maintenance,utilities,repairs,cleaning,security,tax_fee,insurance,management,supplies,other',
            'description' => 'required|string|min:5|max:2000',
            'expense_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expense = $this->expenses->create($data);

        return response()->json(
            ['message' => 'Expense created.', 'data' => $this->payload($expense)],
            201
        );
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
        ]);

        // Fine-grained permission by target state.
        $user = $request->user();
        $to = $data['to'];
        if (in_array($to, ['approved', 'rejected'], true) && ! $user->hasPermission('expenses.approve')) {
            abort(403, 'Approval permission required.');
        }
        if ($to === 'posted' && ! $user->hasPermission('expenses.post')) {
            abort(403, 'Posting permission required.');
        }
        if ($to === 'submitted' && ! $user->hasPermission('expenses.create')) {
            abort(403);
        }

        $expense = $this->expenses->transition(
            $this->expenses->find($id), $to
        );

        return response()->json(['message' => "Expense {$expense->status}.", 'data' => $this->payload($expense)]);
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $expense = $this->expenses->reverse($this->expenses->find($id), $data['reason']);

        return response()->json(['message' => 'Expense reversed.', 'data' => $this->payload($expense)]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->expenses->summary($request->only([
            'property_id', 'from', 'to',
        ]))]);
    }

    private function payload($e): array
    {
        return [
            'id' => $e->id,
            'expense_number' => $e->expense_number,
            'property' => $e->property ? ['id' => $e->property->id, 'name' => $e->property->name] : null,
            'vendor' => $e->vendor ? ['id' => $e->vendor->id, 'name' => $e->vendor->name] : null,
            'category' => $e->category,
            'description' => $e->description,
            'expense_date' => $e->expense_date->toDateString(),
            'amount' => (float) $e->amount,
            'currency' => $e->currency,
            'status' => $e->status,
            'submitted_by' => $e->submittedBy?->name,
            'approved_by' => $e->approvedBy?->name,
        ];
    }
}
