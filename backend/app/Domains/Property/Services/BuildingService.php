<?php

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

class BuildingService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Building::withCount('units')
            ->with('property:id,name')
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_dir'] ?? 'asc');

        PropertyAccess::applyToPropertyQuery($query, $this->actor, 'property_id');

        if (! empty($filters['property_id'])) {
            $property = $this->resolveProperty((int) $filters['property_id']);
            $query->where('property_id', $property->id);
        }
        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Building
    {
        $query = Building::with(['property', 'units']);
        PropertyAccess::applyToPropertyQuery($query, $this->actor, 'property_id');

        return $query->findOrFail($id);
    }

    public function create(array $data): Building
    {
        $property = $this->resolveProperty((int) $data['property_id']);

        if ($property->trashed()) {
            abort(422, 'Cannot add a building to an archived property.');
        }

        $building = DB::transaction(fn () => Building::create([
            ...$data,
            'agency_id' => $property->agency_id,
            'property_id' => $property->id,
        ]));

        $this->audit()->logModelChange('buildings.create', $building);

        return $building->fresh();
    }

    public function update(Building $building, array $data): Building
    {
        $this->ensureBuildingAccess($building);

        // property_id is immutable — a building never moves between properties.
        unset($data['property_id']);

        $old = $building->toArray();
        $building->update($data);

        $this->audit()->log('buildings.update', $building, $old);

        return $building->fresh();
    }

    /** Archive = soft delete, cascading to units. Documents preserved. */
    public function archive(Building $building): Building
    {
        $this->ensureBuildingAccess($building);

        DB::transaction(function () use ($building) {
            $building->units()->delete();
            $building->delete();
        });

        $this->audit()->log('buildings.archive', $building);

        return $building;
    }

    public function restore(int $id): Building
    {
        $query = Building::onlyTrashed()->with('property');
        PropertyAccess::applyToPropertyQuery($query, $this->actor, 'property_id');
        $building = $query->findOrFail($id);

        if ($building->property && $building->property->trashed()) {
            abort(422, 'Cannot restore a building whose property is archived. Restore the property first.');
        }

        DB::transaction(function () use ($building) {
            $building->restore();
            $building->units()->onlyTrashed()->restore();
        });

        $this->audit()->log('buildings.restore', $building);

        return $building->fresh();
    }

    /**
     * Resolve a property by ID, enforcing agency + portfolio access.
     * Returns 404 for anything outside the actor's reach (no existence leak).
     */
    public function resolveProperty(int $propertyId): Property
    {
        $query = Property::query();
        PropertyAccess::applyToPropertyQuery($query, $this->actor);

        return $query->findOrFail($propertyId);
    }

    public function ensureBuildingAccess(Building $building): void
    {
        $this->ensureAgencyAccess($building->agency_id);

        $property = $building->property;
        if (! $property) {
            abort(404);
        }
        app(PropertyService::class)->ensurePropertyAccess($property);
    }
}
