<?php

namespace App\Domains\Property\Services;

use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * PropertyService — the single place for Property business rules.
 * Controllers stay thin; agency isolation comes from AgencyScope;
 * portfolio scoping (owner/tenant) comes from PropertyAccess.
 */
class PropertyService extends DomainService
{
    public function list(array $filters = [])
    {
        $query = Property::withCount(['buildings', 'units'])
            ->with('owner:id,name,email')
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_dir'] ?? 'asc');

        PropertyAccess::applyToPropertyQuery($query, $this->actor());

        if (! empty($filters['search'])) {
            $s = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)
                ->orWhere('city', 'like', $s)
                ->orWhere('address', 'like', $s));
        }
        foreach (['property_type', 'status', 'city'] as $f) {
            if (! empty($filters[$f])) {
                $query->where($f, $filters[$f]);
            }
        }
        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): Property
    {
        $query = Property::with(['owner:id,name,email', 'buildings', 'units'])
            ->withCount(['buildings', 'units']);
        PropertyAccess::applyToPropertyQuery($query, $this->actor());

        return $query->findOrFail($id); // 404 — never leaks existence
    }

    public function create(array $data): Property
    {
        $actor = $this->actor();

        $agencyId = $actor->isSuperAdmin() ? ($data['agency_id'] ?? null) : $actor->agency_id;
        if (! $agencyId) {
            abort(422, 'A property must belong to an agency.');
        }
        $this->ensureAgencyAccess($agencyId);

        if (! empty($data['owner_id'])) {
            $this->ensureOwnerInAgency((int) $data['owner_id'], $agencyId);
        }

        $property = DB::transaction(fn () => Property::create([
            ...$data,
            'agency_id' => $agencyId,
        ]));

        $this->audit()->logModelChange('properties.create', $property);

        return $property->fresh();
    }

    public function update(Property $property, array $data): Property
    {
        $this->ensurePropertyAccess($property);

        if (array_key_exists('owner_id', $data) && ! empty($data['owner_id'])) {
            $this->ensureOwnerInAgency((int) $data['owner_id'], $property->agency_id);
        }

        $old = $property->toArray();
        $property->update($data);

        $this->audit()->log('properties.update', $property, $old);

        return $property->fresh();
    }

    /**
     * Archive = soft delete, cascading to buildings and units.
     * Documents are preserved as evidence. Nothing is hard-deleted.
     */
    public function archive(Property $property): Property
    {
        $this->ensurePropertyAccess($property);

        // P3: active leases are live agreements — terminate them first.
        if ($this->hasActiveLeases($property->id)) {
            abort(422, 'Cannot archive a property with active leases. Terminate the leases first.');
        }

        DB::transaction(function () use ($property) {
            $property->units()->delete();
            $property->buildings()->delete();
            $property->delete();
        });

        $this->audit()->log('properties.archive', $property);

        return $property;
    }

    /** Restore an archived property together with its archived children. */
    public function restore(int $id): Property
    {
        $query = Property::onlyTrashed()->with(['buildings', 'units']);
        PropertyAccess::applyToPropertyQuery($query, $this->actor());
        $property = $query->findOrFail($id);

        DB::transaction(function () use ($property) {
            $property->restore();
            $property->buildings()->onlyTrashed()->restore();
            $property->units()->onlyTrashed()->restore();
        });

        $this->audit()->log('properties.restore', $property);

        return $property->fresh();
    }

    /** Real dashboard numbers: agency-scoped, portfolio-scoped, no fabrication. */
    public function stats(): array
    {
        $actor = $this->actor();
        $ids = PropertyAccess::accessiblePropertyIds($actor);

        $properties = Property::query();
        $buildings = \App\Domains\Property\Models\Building::query();
        $units = Unit::query();

        if (! is_null($ids)) {
            $properties->whereIn('id', $ids);
            $buildings->whereIn('property_id', $ids);
            $units->whereIn('property_id', $ids);
        }
        // Note: AgencyScope already constrains non-super-admins to their agency.

        $stats = [
            'total_properties' => (clone $properties)->count(),
            'total_buildings' => (clone $buildings)->count(),
            'total_units' => (clone $units)->count(),
            'vacant_units' => (clone $units)->where('status', 'vacant')->count(),
            'occupied_units' => (clone $units)->where('status', 'occupied')->count(),
            'units_by_status' => (clone $units)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all(),
        ];

        // P3 — leasing metrics (same agency/portfolio scoping).
        $stats = array_merge($stats, app(\App\Domains\Leasing\Services\DashboardLeasingMetrics::class)->forActor($actor, $ids));

        return $stats;
    }

    private function hasActiveLeases(int $propertyId): bool
    {
        return \App\Domains\Leasing\Models\Lease::withoutAgencyScope()
            ->where('property_id', $propertyId)
            ->where('status', 'active')
            ->exists();
    }

    /** Guard: actor may access this property (agency + portfolio). */
    public function ensurePropertyAccess(Property $property): void
    {
        $this->ensureAgencyAccess($property->agency_id);

        $ids = PropertyAccess::accessiblePropertyIds($this->actor());
        if (! is_null($ids) && ! in_array($property->id, $ids, true)) {
            abort(404); // never leak existence outside the portfolio
        }
    }

    private function ensureOwnerInAgency(int $ownerId, int $agencyId): void
    {
        $owner = \App\Domains\Shared\Models\User::withoutGlobalScopes()
            ->where('id', $ownerId)
            ->where('agency_id', $agencyId)
            ->first();

        if (! $owner) {
            abort(422, 'The selected owner does not belong to this agency.');
        }
    }
}
