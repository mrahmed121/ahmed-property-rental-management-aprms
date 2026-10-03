<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\FinancialPeriodService;
use App\Domains\Billing\Services\LateFeeService;
use App\Domains\Billing\Services\RentCycleService;
use App\Domains\Billing\Services\RentInvoiceService;
use App\Domains\Leasing\Models\Lease;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private RentInvoiceService $invoices,
        private RentCycleService $cycle,
        private LateFeeService $lateFees,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->invoices->list($request->only([
            'tenant_id', 'lease_id', 'unit_id', 'property_id', 'status',
            'overdue', 'from', 'to', 'min_amount', 'max_amount', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($i) => $this->payload($i)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->invoices->find($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lease_id' => 'required|integer|exists:leases,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date',
            'base_rent' => 'required|numeric|min:0',
            'utilities' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lease = Lease::findOrFail($data['lease_id']);
        $invoice = $this->invoices->create($lease, $data);

        return response()->json(
            ['message' => 'Invoice created.', 'data' => $this->payload($invoice)],
            201
        );
    }

    public function void(int $id): JsonResponse
    {
        $invoice = $this->invoices->void($this->invoices->find($id));

        return response()->json(['message' => 'Invoice voided.', 'data' => $this->payload($invoice)]);
    }

    /** Run the rent cycle for a period (or dry-run). */
    public function generateCycle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'dry_run' => 'nullable|boolean',
        ]);

        $result = $this->cycle->generateForPeriod($data['period'], (bool) ($data['dry_run'] ?? false));

        return response()->json(['data' => $result]);
    }

    /** Accrue late fees for overdue invoices. */
    public function accrueLateFees(): JsonResponse
    {
        $fees = $this->lateFees->accrueOverdue();

        return response()->json([
            'message' => count($fees).' late fee(s) accrued.',
            'data' => array_map(fn ($f) => [
                'id' => $f->id, 'invoice_id' => $f->invoice_id,
                'amount' => (float) $f->amount,
            ], $fees),
        ]);
    }

    public function waiveLateFee(int $id): JsonResponse
    {
        $fee = \App\Domains\Billing\Models\LateFee::findOrFail($id);
        $fee = $this->lateFees->waive($fee);

        return response()->json(['message' => 'Late fee waived.', 'data' => ['id' => $fee->id]]);
    }

    private function payload($i): array
    {
        return [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'tenant' => $i->tenant ? ['id' => $i->tenant->id, 'name' => $i->tenant->first_name.' '.$i->tenant->last_name] : null,
            'unit' => $i->unit ? ['id' => $i->unit->id, 'unit_number' => $i->unit->unit_number] : null,
            'property' => $i->property ? ['id' => $i->property->id, 'name' => $i->property->name] : null,
            'lease_id' => $i->lease_id,
            'period_start' => $i->period_start?->toDateString(),
            'period_end' => $i->period_end?->toDateString(),
            'issue_date' => $i->issue_date?->toDateString(),
            'due_date' => $i->due_date?->toDateString(),
            'base_rent' => (float) $i->base_rent,
            'utilities' => (float) $i->utilities,
            'other_charges' => (float) $i->other_charges,
            'late_fee' => (float) $i->late_fee,
            'total' => (float) $i->total,
            'paid_amount' => (float) $i->paid_amount,
            'outstanding' => $i->outstanding(),
            'status' => $i->status,
            'currency' => $i->currency,
        ];
    }

    private function detailedPayload($i): array
    {
        $data = $this->payload($i);
        $data['notes'] = $i->notes;
        $data['lease'] = $i->lease ? ['id' => $i->lease->id, 'lease_number' => $i->lease->lease_number] : null;
        $data['late_fee_record'] = $i->lateFeeRecord ? [
            'id' => $i->lateFeeRecord->id, 'amount' => (float) $i->lateFeeRecord->amount,
            'status' => $i->lateFeeRecord->status,
        ] : null;
        $data['dunning'] = $i->dunningReminders->map(fn ($d) => [
            'id' => $d->id, 'stage' => $d->stage, 'status' => $d->status,
        ])->all();

        return $data;
    }
}
