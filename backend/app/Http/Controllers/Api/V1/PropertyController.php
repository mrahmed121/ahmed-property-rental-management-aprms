<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Property\Services\PropertyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePropertyRequest;
use App\Http\Requests\Api\V1\UpdatePropertyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(private PropertyService $properties) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->properties->list($request->only([
            'search', 'property_type', 'status', 'city', 'owner_id',
            'sort_by', 'sort_dir', 'per_page',
        ]));

        return response()->json([
            'data' => $result->map(fn ($p) => $this->payload($p)),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->properties->create($request->validated());

        return response()->json([
            'message' => 'Property created.',
            'data' => $this->payload($property),
        ], 201);
    }

    public function show(int $property): JsonResponse
    {
        return response()->json([
            'data' => $this->detailedPayload($this->properties->find($property)),
        ]);
    }

    public function update(UpdatePropertyRequest $request, int $property): JsonResponse
    {
        $model = $this->properties->find($property);
        $updated = $this->properties->update($model, $request->validated());

        return response()->json([
            'message' => 'Property updated.',
            'data' => $this->payload($updated),
        ]);
    }

    /** Archive (soft delete, cascades to buildings/units). */
    public function destroy(int $property): JsonResponse
    {
        $model = $this->properties->find($property);
        $this->properties->archive($model);

        return response()->json(['message' => 'Property archived.']);
    }

    public function restore(int $property): JsonResponse
    {
        $restored = $this->properties->restore($property);

        return response()->json([
            'message' => 'Property restored.',
            'data' => $this->payload($restored),
        ]);
    }

    private function payload($p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'property_type' => $p->property_type,
            'address' => $p->address,
            'city' => $p->city,
            'postal_code' => $p->postal_code,
            'status' => $p->status,
            'owner' => $p->owner ? ['id' => $p->owner->id, 'name' => $p->owner->name] : null,
            'buildings_count' => $p->buildings_count ?? null,
            'units_count' => $p->units_count ?? null,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function detailedPayload($p): array
    {
        $data = $this->payload($p);
        $data['description'] = $p->description;
        $data['notes'] = $p->notes;
        $data['buildings'] = $p->buildings->map(fn ($b) => [
            'id' => $b->id, 'name' => $b->name, 'floors' => $b->floors, 'status' => $b->status,
        ])->all();

        return $data;
    }
}
