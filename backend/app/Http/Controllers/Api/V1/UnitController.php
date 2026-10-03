<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Property\Services\UnitService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUnitRequest;
use App\Http\Requests\Api\V1\UpdateUnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(private UnitService $units) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->units->list($request->only([
            'building_id', 'property_id', 'search', 'status', 'unit_type',
            'sort_by', 'sort_dir', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($u) => $this->payload($u)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->units->create($request->validated());

        return response()->json([
            'message' => 'Unit created.',
            'data' => $this->payload($unit),
        ], 201);
    }

    public function show(int $unit): JsonResponse
    {
        return response()->json(['data' => $this->detailedPayload($this->units->find($unit))]);
    }

    public function update(UpdateUnitRequest $request, int $unit): JsonResponse
    {
        $model = $this->units->find($unit);
        $updated = $this->units->update($model, $request->validated());

        return response()->json([
            'message' => 'Unit updated.',
            'data' => $this->payload($updated),
        ]);
    }

    public function destroy(int $unit): JsonResponse
    {
        $model = $this->units->find($unit);
        $this->units->archive($model);

        return response()->json(['message' => 'Unit archived.']);
    }

    public function restore(int $unit): JsonResponse
    {
        $restored = $this->units->restore($unit);

        return response()->json([
            'message' => 'Unit restored.',
            'data' => $this->payload($restored),
        ]);
    }

    private function payload($u): array
    {
        return [
            'id' => $u->id,
            'building_id' => $u->building_id,
            'property_id' => $u->property_id,
            'building' => isset($u->building) ? ['id' => $u->building->id, 'name' => $u->building->name] : null,
            'property' => isset($u->property) ? ['id' => $u->property->id, 'name' => $u->property->name] : null,
            'unit_number' => $u->unit_number,
            'floor' => $u->floor,
            'unit_type' => $u->unit_type,
            'area_sqft' => $u->area_sqft,
            'bedrooms' => $u->bedrooms,
            'bathrooms' => $u->bathrooms,
            'status' => $u->status,
            'market_rent' => $u->market_rent,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }

    private function detailedPayload($u): array
    {
        $data = $this->payload($u);
        $data['notes'] = $u->notes;
        $data['documents'] = $u->documents->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name, 'document_type' => $d->document_type,
        ])->all();

        return $data;
    }
}
