<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Property\Services\BuildingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBuildingRequest;
use App\Http\Requests\Api\V1\UpdateBuildingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function __construct(private BuildingService $buildings) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->buildings->list($request->only([
            'property_id', 'search', 'status', 'sort_by', 'sort_dir', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($b) => $this->payload($b)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = $this->buildings->create($request->validated());

        return response()->json([
            'message' => 'Building created.',
            'data' => $this->payload($building),
        ], 201);
    }

    public function show(int $building): JsonResponse
    {
        $model = $this->buildings->find($building);

        return response()->json(['data' => $this->detailedPayload($model)]);
    }

    public function update(UpdateBuildingRequest $request, int $building): JsonResponse
    {
        $model = $this->buildings->find($building);
        $updated = $this->buildings->update($model, $request->validated());

        return response()->json([
            'message' => 'Building updated.',
            'data' => $this->payload($updated),
        ]);
    }

    public function destroy(int $building): JsonResponse
    {
        $model = $this->buildings->find($building);
        $this->buildings->archive($model);

        return response()->json(['message' => 'Building archived.']);
    }

    public function restore(int $building): JsonResponse
    {
        $restored = $this->buildings->restore($building);

        return response()->json([
            'message' => 'Building restored.',
            'data' => $this->payload($restored),
        ]);
    }

    private function payload($b): array
    {
        return [
            'id' => $b->id,
            'property_id' => $b->property_id,
            'property' => isset($b->property) ? ['id' => $b->property->id, 'name' => $b->property->name] : null,
            'name' => $b->name,
            'floors' => $b->floors,
            'status' => $b->status,
            'units_count' => $b->units_count ?? null,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    private function detailedPayload($b): array
    {
        $data = $this->payload($b);
        $data['description'] = $b->description;
        $data['notes'] = $b->notes;
        $data['units'] = $b->units->map(fn ($u) => [
            'id' => $u->id, 'unit_number' => $u->unit_number,
            'unit_type' => $u->unit_type, 'status' => $u->status,
            'market_rent' => $u->market_rent,
        ])->all();

        return $data;
    }
}
