<?php

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

class UnitService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Unit::with(['building:id,name', 'property:id,name'])
            ->orderBy($filters['sort_by'] ?? 'unit_number', $filters['sort_dir'] ?? 'asc');

        PropertyAccess::applyToPropertyQuery($query, $this->actor(), 'property_id');

        if (! empty($filters['building_id'])) {
            $building = $this->resolveBuilding((int) $filters['building_id']);
            $query->where('building_id', $building->id);
        }
        if (! empty($filters['property_id'])) {
            $property = app(BuildingService::class)->resolveProperty((int) $filters['property_id']);
            $query->where('property_id', $property->id);
        }
        if (! empty($filters['search'])) {
            $query->where('unit_number', 'like', '%'.$filters['search'].'%');
        }
        foreach (['status', 'unit_type'] as $f) {
            if (! empty($filters[$f])) {
                $query->where($f, $filters[$f]);
            }
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Unit
    {
        $query = Unit::with(['building', 'property', 'documents']);
        PropertyAccess::applyToPropertyQuery($query, $this->actor(), 'property_id');

        return $query->findOrFail($id);
    }

    public function create(array $data): Unit
    {
        $building = $this->resolveBuilding((int) $data['building_id']);

        if ($building->trashed() || ($building->property && $building->property->trashed())) {
            abort(422, 'Cannot add a unit to an archived building or property.');
        }

        $unit = DB::transaction(fn () => Unit::create([
            ...$data,
            'agency_id' => $building->agency_id,
            'building_id' => $building->id,
            'property_id' => $building->property_id,
        ]));

        $this->audit()->logModelChange('units.create', $unit);

        return $unit->fresh();
    }

    public function update(Unit $unit, array $data): Unit
    {
        $this->ensureUnitAccess($unit);

        // building_id is immutable — a unit never moves between buildings.
        unset($data['building_id']);

        $old = $unit->toArray();
        $unit->update($data);

        $this->audit()->log('units.update', $unit, $old);

        return $unit->fresh();
    }

    /** Archive = soft delete. No hard deletes in P2. */
    public function archive(Unit $unit): Unit
    {
        $this->ensureUnitAccess($unit);

        if (\App\Domains\Leasing\Models\Lease::withoutAgencyScope()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')->exists()) {
            abort(422, 'Cannot archive a unit with active leases. Terminate the lease first.');
        }

        $unit->delete();

        $this->audit()->log('units.archive', $unit);

        return $unit;
    }

    public function restore(int $id): Unit
    {
        $query = Unit::onlyTrashed()->with(['building', 'property']);
        PropertyAccess::applyToPropertyQuery($query, $this->actor(), 'property_id');
        $unit = $query->findOrFail($id);

        if (($unit->building && $unit->building->trashed())
            || ($unit->property && $unit->property->trashed())) {
            abort(422, 'Cannot restore a unit whose building or property is archived.');
        }

        $unit->restore();
        $this->audit()->log('units.restore', $unit);

        return $unit->fresh();
    }

    public function resolveBuilding(int $buildingId): Building
    {
        $query = Building::query();
        PropertyAccess::applyToPropertyQuery($query, $this->actor(), 'property_id');

        return $query->findOrFail($buildingId);
    }

    public function ensureUnitAccess(Unit $unit): void
    {
        $this->ensureAgencyAccess($unit->agency_id);

        $property = $unit->property;
        if (! $property) {
            abort(404);
        }
        app(PropertyService::class)->ensurePropertyAccess($property);
    }
}
