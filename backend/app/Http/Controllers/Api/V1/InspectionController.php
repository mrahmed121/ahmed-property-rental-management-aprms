<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Leasing\Services\MoveOutInspectionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInspectionRequest;
use App\Http\Requests\Api\V1\UpdateInspectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function __construct(private MoveOutInspectionService $inspections) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->inspections->list($request->only(['lease_id', 'review_status', 'per_page']));

        return response()->json([
            'data' => $result->map(fn ($i) => $this->payload($i)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $inspection = $this->inspections->create((int) $data['lease_id'], $data);

        return response()->json([
            'message' => 'Move-out inspection recorded.',
            'data' => $this->payload($inspection),
        ], 201);
    }

    public function show(int $inspection): JsonResponse
    {
        $model = $this->inspections->find($inspection);

        return response()->json(['data' => [
            ...$this->payload($model),
            'notes' => $model->notes,
            'damage_observations' => $model->damage_observations,
            'reviewed_at' => $model->reviewed_at?->toIso8601String(),
            'reviewed_by' => $model->reviewedBy ? ['id' => $model->reviewedBy->id, 'name' => $model->reviewedBy->name] : null,
            'lease' => $model->lease ? [
                'id' => $model->lease->id,
                'lease_number' => $model->lease->lease_number,
                'tenant' => $model->lease->tenant ? $model->lease->tenant->fullName() : null,
                'unit' => $model->lease->unit ? $model->lease->unit->unit_number : null,
            ] : null,
        ]]);
    }

    public function update(UpdateInspectionRequest $request, int $inspection): JsonResponse
    {
        $model = $this->inspections->find($inspection);
        $updated = $this->inspections->update($model, $request->validated());

        return response()->json([
            'message' => 'Inspection updated.',
            'data' => $this->payload($updated),
        ]);
    }

    public function review(int $inspection): JsonResponse
    {
        $model = $this->inspections->find($inspection);
        $reviewed = $this->inspections->review($model);

        return response()->json([
            'message' => 'Inspection marked as reviewed.',
            'data' => $this->payload($reviewed),
        ]);
    }

    private function payload($i): array
    {
        return [
            'id' => $i->id,
            'lease_id' => $i->lease_id,
            'lease_number' => $i->lease?->lease_number,
            'tenant' => $i->lease?->tenant ? $i->lease->tenant->fullName() : null,
            'inspection_date' => $i->inspection_date?->toDateString(),
            'condition' => $i->condition,
            'review_status' => $i->review_status,
            'created_at' => $i->created_at?->toIso8601String(),
        ];
    }
}
