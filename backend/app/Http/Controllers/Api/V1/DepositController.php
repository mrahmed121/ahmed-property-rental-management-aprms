<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Deposits\Models\Deposit;
use App\Domains\Deposits\Models\DepositDeduction;
use App\Domains\Deposits\Services\DepositService;
use App\Domains\Deposits\Services\DepositSettlementService;
use App\Domains\Leasing\Models\Lease;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(
        private DepositService $deposits,
        private DepositSettlementService $settlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->deposits->list($request->only([
            'tenant_id', 'lease_id', 'status', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($d) => $this->payload($d)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->deposits->find($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lease_id' => 'required|integer|exists:leases,id',
            'deposit_amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lease = Lease::findOrFail($data['lease_id']);
        $deposit = $this->deposits->create($lease, $data);

        return response()->json(
            ['message' => 'Deposit recorded.', 'data' => $this->payload($deposit)],
            201
        );
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'received_date' => 'nullable|date|before_or_equal:today',
            'reason' => 'nullable|string|max:1000',
        ]);

        $deposit = $this->deposits->receive($this->deposits->find($id), $data);

        return response()->json(['message' => 'Deposit received.', 'data' => $this->payload($deposit)]);
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'deposit_amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $deposit = $this->deposits->adjust($this->deposits->find($id), $data);

        return response()->json(['message' => 'Deposit adjusted.', 'data' => $this->payload($deposit)]);
    }

    // --- Deductions ---

    public function proposeDeduction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:damage,cleaning,unpaid_rent,other',
            'assessment' => 'nullable|in:wear,damage',
            'description' => 'required|string|min:5|max:2000',
            'amount' => 'required|numeric|min:0.01',
            'inspection_id' => 'nullable|integer|exists:move_out_inspections,id',
        ]);

        $deduction = $this->settlements->proposeDeduction($this->deposits->find($id), $data);

        return response()->json(
            ['message' => 'Deduction proposed.', 'data' => $this->deductionPayload($deduction)],
            201
        );
    }

    public function reviewDeduction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
        ]);

        $deduction = $this->settlements->reviewDeduction(
            DepositDeduction::findOrFail($id), $data['decision']
        );

        return response()->json(['message' => "Deduction {$data['decision']}.", 'data' => $this->deductionPayload($deduction)]);
    }

    // --- Settlement ---

    public function draftSettlement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'inspection_id' => 'required|integer|exists:move_out_inspections,id',
        ]);

        $settlement = $this->settlements->draft($this->deposits->find($id), $data['inspection_id']);

        return response()->json(
            ['message' => 'Settlement drafted.', 'data' => $this->settlementPayload($settlement)],
            201
        );
    }

    public function previewSettlement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'apply_to_balance' => 'nullable|numeric|min:0',
        ]);

        $deposit = $this->deposits->find($id);
        $settlement = $deposit->settlement ?? abort(422, 'No settlement drafted yet.');

        return response()->json(['data' => $this->settlements->preview(
            $settlement, (float) ($data['apply_to_balance'] ?? 0)
        )]);
    }

    public function finalizeSettlement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'apply_to_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $deposit = $this->deposits->find($id);
        $settlement = $deposit->settlement ?? abort(422, 'No settlement drafted yet.');
        $settlement = $this->settlements->finalize($settlement, $data);

        return response()->json(['message' => 'Settlement finalized and locked.', 'data' => $this->settlementPayload($settlement)]);
    }

    public function reverseSettlement(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $deposit = $this->deposits->find($id);
        $settlement = $deposit->settlement ?? abort(422, 'No settlement found.');
        $settlement = $this->settlements->reverse($settlement, $data['reason']);

        return response()->json(['message' => 'Settlement reversed.', 'data' => $this->settlementPayload($settlement)]);
    }

    private function payload($d): array
    {
        return [
            'id' => $d->id,
            'tenant' => $d->tenant ? ['id' => $d->tenant->id, 'name' => $d->tenant->first_name.' '.$d->tenant->last_name] : null,
            'lease_id' => $d->lease_id,
            'unit' => $d->unit ? ['id' => $d->unit->id, 'unit_number' => $d->unit->unit_number] : null,
            'property' => $d->property ? ['id' => $d->property->id, 'name' => $d->property->name] : null,
            'deposit_amount' => (float) $d->deposit_amount,
            'held_amount' => (float) $d->held_amount,
            'status' => $d->status,
            'currency' => $d->currency,
            'received_date' => $d->received_date?->toDateString(),
            'release_date' => $d->release_date?->toDateString(),
            'reference' => $d->reference,
        ];
    }

    private function detailedPayload($d): array
    {
        $data = $this->payload($d);
        $data['notes'] = $d->notes;
        $data['transactions'] = $d->transactions->map(fn ($t) => [
            'id' => $t->id, 'type' => $t->type, 'amount' => (float) $t->amount,
            'balance_after' => (float) $t->balance_after,
            'reason' => $t->reason,
            'created_by' => $t->createdBy?->name,
            'created_at' => $t->created_at?->toDateTimeString(),
        ])->all();
        $data['deductions'] = $d->deductions->map(fn ($x) => $this->deductionPayload($x))->all();
        $data['settlement'] = $d->settlement ? $this->settlementPayload($d->settlement) : null;

        return $data;
    }

    private function deductionPayload($x): array
    {
        return [
            'id' => $x->id,
            'category' => $x->category,
            'assessment' => $x->assessment,
            'description' => $x->description,
            'amount' => (float) $x->amount,
            'status' => $x->status,
            'inspection_id' => $x->inspection_id,
            'reviewed_by' => $x->reviewedBy?->name,
        ];
    }

    private function settlementPayload($s): array
    {
        return [
            'id' => $s->id,
            'deposit_id' => $s->deposit_id,
            'inspection_id' => $s->inspection_id,
            'gross_deposit' => (float) $s->gross_deposit,
            'total_deductions' => (float) $s->total_deductions,
            'applied_to_balance' => (float) $s->applied_to_balance,
            'refund_amount' => (float) $s->refund_amount,
            'status' => $s->status,
            'finalized_at' => $s->finalized_at?->toDateTimeString(),
            'approved_by' => $s->approvedBy?->name,
            'notes' => $s->notes,
        ];
    }
}
