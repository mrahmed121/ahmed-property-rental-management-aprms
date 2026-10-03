<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Utilities\Services\UtilityBillingService;
use App\Domains\Utilities\Services\UtilityMeterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    public function __construct(
        private UtilityMeterService $meters,
        private UtilityBillingService $billing,
    ) {}

    // --- Meters ---

    public function indexMeters(Request $request): JsonResponse
    {
        $result = $this->meters->listMeters($request->only([
            'property_id', 'utility_type', 'status', 'search', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($m) => $this->meterPayload($m)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function showMeter(int $id): JsonResponse
    {
        $m = $this->meters->findMeter($id);
        $data = $this->meterPayload($m);
        $data['readings'] = $m->readings->map(fn ($r) => [
            'id' => $r->id,
            'reading_date' => $r->reading_date->toDateString(),
            'reading_value' => (float) $r->reading_value,
            'recorded_by' => $r->recordedBy?->name,
            'source' => $r->source,
        ])->all();
        $data['bills'] = $m->bills->map(fn ($b) => [
            'id' => $b->id, 'bill_number' => $b->bill_number,
            'period_start' => $b->period_start->toDateString(),
            'total' => (float) $b->total, 'status' => $b->status,
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function storeMeter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'meter_number' => 'required|string|max:100',
            'utility_type' => 'required|in:electricity,gas,water,other',
            'unit_of_measure' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
            'installation_date' => 'nullable|date|before_or_equal:today',
            'opening_reading' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $meter = $this->meters->createMeter($data);

        return response()->json(
            ['message' => 'Meter registered.', 'data' => $this->meterPayload($meter)],
            201
        );
    }

    // --- Readings ---

    public function indexReadings(Request $request, int $id): JsonResponse
    {
        $meter = $this->meters->findMeter($id);
        $result = $this->meters->listReadings($meter, $request->only(['from', 'to', 'per_page']));

        return response()->json([
            'data' => $result->map(fn ($r) => [
                'id' => $r->id,
                'reading_date' => $r->reading_date->toDateString(),
                'reading_value' => (float) $r->reading_value,
                'recorded_by' => $r->recordedBy?->name,
                'source' => $r->source,
                'notes' => $r->notes,
            ]),
            'meta' => ['current_page' => $result->currentPage(), 'total' => $result->total()],
        ]);
    }

    public function storeReading(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reading_date' => 'required|date|before_or_equal:today',
            'reading_value' => 'required|numeric|min:0',
            'source' => 'nullable|in:manual,import,estimate',
            'notes' => 'nullable|string|max:1000',
        ]);

        $reading = $this->meters->recordReading($this->meters->findMeter($id), $data);

        return response()->json([
            'message' => 'Reading recorded.',
            'data' => [
                'id' => $reading->id,
                'reading_date' => $reading->reading_date->toDateString(),
                'reading_value' => (float) $reading->reading_value,
            ],
        ], 201);
    }

    public function consumption(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return response()->json(['data' => $this->meters->consumption(
            $this->meters->findMeter($id), $data['from'], $data['to']
        )]);
    }

    // --- Bills ---

    public function indexBills(Request $request): JsonResponse
    {
        $result = $this->billing->listBills($request->only([
            'meter_id', 'property_id', 'tenant_id', 'status', 'utility_type', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($b) => $this->billPayload($b)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function showBill(int $id): JsonResponse
    {
        $b = $this->billing->findBill($id);
        $data = $this->billPayload($b);
        $data['allocations'] = $b->allocations->map(fn ($a) => [
            'id' => $a->id,
            'unit' => $a->unit ? $a->unit->unit_number : null,
            'tenant' => $a->tenant ? $a->tenant->first_name.' '.$a->tenant->last_name : null,
            'allocation_type' => $a->allocation_type,
            'consumption_share' => (float) $a->consumption_share,
            'amount' => (float) $a->amount,
            'notes' => $a->notes,
        ])->all();

        return response()->json(['data' => $data]);
    }

    public function previewBill(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'rate' => 'required|numeric|min:0',
            'fixed_charge' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        return response()->json(['data' => $this->billing->preview(
            $this->meters->findMeter($id), $data
        )]);
    }

    public function generateBill(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'rate' => 'required|numeric|min:0',
            'fixed_charge' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'allocation_method' => 'nullable|in:metered,equal_split,area_based,custom',
            'notes' => 'nullable|string|max:1000',
        ]);

        $bill = $this->billing->generate($this->meters->findMeter($id), $data);

        return response()->json(
            ['message' => 'Utility bill generated.', 'data' => $this->billPayload($bill)],
            201
        );
    }

    public function allocateBill(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.unit_id' => 'nullable|integer|exists:units,id',
            'lines.*.tenant_id' => 'nullable|integer|exists:tenants,id',
            'lines.*.allocation_type' => 'required|in:metered,equal_split,area_based,custom,vacant_owner',
            'lines.*.consumption_share' => 'nullable|numeric|min:0',
            'lines.*.amount' => 'required|numeric|min:0',
            'lines.*.notes' => 'nullable|string|max:500',
        ]);

        $bill = $this->billing->allocate($this->billing->findBill($id), $data['lines']);

        return response()->json(['message' => 'Bill allocated.', 'data' => $this->billPayload($bill)]);
    }

    public function finalizeBill(int $id): JsonResponse
    {
        $bill = $this->billing->finalize($this->billing->findBill($id));

        return response()->json(['message' => 'Bill finalized.', 'data' => $this->billPayload($bill)]);
    }

    public function reverseBill(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $bill = $this->billing->reverse($this->billing->findBill($id), $data['reason']);

        return response()->json(['message' => 'Bill reversed.', 'data' => $this->billPayload($bill)]);
    }

    private function meterPayload($m): array
    {
        return [
            'id' => $m->id,
            'meter_number' => $m->meter_number,
            'utility_type' => $m->utility_type,
            'unit_of_measure' => $m->unit_of_measure,
            'status' => $m->status,
            'property' => $m->property ? ['id' => $m->property->id, 'name' => $m->property->name] : null,
            'unit' => $m->unit ? ['id' => $m->unit->id, 'unit_number' => $m->unit->unit_number] : null,
            'installation_date' => $m->installation_date?->toDateString(),
            'opening_reading' => (float) $m->opening_reading,
        ];
    }

    private function billPayload($b): array
    {
        return [
            'id' => $b->id,
            'bill_number' => $b->bill_number,
            'meter' => $b->meter ? ['id' => $b->meter->id, 'meter_number' => $b->meter->meter_number, 'utility_type' => $b->meter->utility_type] : null,
            'property' => $b->property ? ['id' => $b->property->id, 'name' => $b->property->name] : null,
            'unit' => $b->unit ? ['id' => $b->unit->id, 'unit_number' => $b->unit->unit_number] : null,
            'tenant' => $b->tenant ? ['id' => $b->tenant->id, 'name' => $b->tenant->first_name.' '.$b->tenant->last_name] : null,
            'period_start' => $b->period_start->toDateString(),
            'period_end' => $b->period_end->toDateString(),
            'previous_reading' => (float) $b->previous_reading,
            'current_reading' => (float) $b->current_reading,
            'consumption' => (float) $b->consumption,
            'rate' => (float) $b->rate,
            'fixed_charge' => (float) $b->fixed_charge,
            'tax_amount' => (float) $b->tax_amount,
            'total' => (float) $b->total,
            'currency' => $b->currency,
            'status' => $b->status,
            'allocation_method' => $b->allocation_method,
            'finalized_at' => $b->finalized_at?->toDateTimeString(),
        ];
    }
}
