<?php

namespace App\Domains\Utilities\Services;

use App\Domains\Leasing\Services\TenantAccess;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Services\DomainService;
use App\Domains\Utilities\Models\MeterReading;
use App\Domains\Utilities\Models\UtilityMeter;
use Illuminate\Support\Facades\DB;

/**
 * UtilityMeterService — meters and readings.
 *
 * Rules:
 * - Reading cannot be negative.
 * - New reading cannot be lower than the previous (no silent rollbacks).
 * - Duplicate (meter, date) prevented by UNIQUE.
 * - Historical readings are never overwritten.
 */
class UtilityMeterService extends DomainService
{
    public function listMeters(array $filters = [])
    {
        $query = UtilityMeter::with(['property:id,name', 'unit:id,unit_number'])
            ->orderBy('meter_number');

        if (! empty($filters['property_id'])) $query->where('property_id', $filters['property_id']);
        if (! empty($filters['utility_type'])) $query->where('utility_type', $filters['utility_type']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['search'])) $query->where('meter_number', 'like', "%{$filters['search']}%");

        $this->applyMeterScope($query);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findMeter(int $id): UtilityMeter
    {
        $meter = UtilityMeter::with([
            'property', 'building', 'unit',
            'readings' => fn ($q) => $q->orderByDesc('reading_date')->limit(12),
            'bills' => fn ($q) => $q->orderByDesc('period_start')->limit(6),
        ])->findOrFail($id);
        $this->ensureMeterAccess($meter);

        return $meter;
    }

    public function createMeter(array $data): UtilityMeter
    {
        $property = Property::findOrFail($data['property_id']);
        $this->ensureAgencyAccess($property->agency_id);

        if (! in_array($data['utility_type'], UtilityMeter::TYPES, true)) {
            abort(422, 'Invalid utility type.');
        }

        $unitId = $data['unit_id'] ?? null;
        if ($unitId) {
            $unit = Unit::findOrFail($unitId);
            if ($unit->agency_id !== $property->agency_id) {
                abort(422, 'Unit does not belong to this property/agency.');
            }
        }

        $opening = round((float) ($data['opening_reading'] ?? 0), 2);
        if ($opening < 0) {
            abort(422, 'Opening reading cannot be negative.');
        }

        $meter = UtilityMeter::create([
            'agency_id' => $property->agency_id,
            'property_id' => $property->id,
            'building_id' => $data['building_id'] ?? null,
            'unit_id' => $unitId,
            'meter_number' => $data['meter_number'],
            'utility_type' => $data['utility_type'],
            'unit_of_measure' => $data['unit_of_measure'] ?? 'kWh',
            'status' => $data['status'] ?? 'active',
            'installation_date' => $data['installation_date'] ?? null,
            'opening_reading' => $opening,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit()->log('utilities.meter_create', $meter, [
            'meter_number' => $meter->meter_number,
        ]);

        return $meter;
    }

    /**
     * Record a reading. Enforces monotonic increase and no duplicates.
     */
    public function recordReading(UtilityMeter $meter, array $data): MeterReading
    {
        $this->ensureMeterAccess($meter);

        return DB::transaction(function () use ($meter, $data) {
            $meter = UtilityMeter::where('id', $meter->id)->lockForUpdate()->firstOrFail();

            $value = round((float) $data['reading_value'], 2);
            if ($value < 0) {
                abort(422, 'Reading cannot be negative.');
            }

            $date = $data['reading_date'];
            if ($date > now()->toDateString()) {
                abort(422, 'Reading date cannot be in the future.');
            }

            // Previous reading (or opening reading).
            $prev = $meter->readings()->orderByDesc('reading_date')->orderByDesc('id')->first();
            $prevValue = $prev ? (float) $prev->reading_value : (float) $meter->opening_reading;

            if ($value < $prevValue - 0.001) {
                abort(422, "Reading ({$value}) cannot be lower than the previous reading ({$prevValue}).");
            }

            // Duplicate date guard (UNIQUE also protects).
            if ($meter->readings()->whereDate('reading_date', $date)->exists()) {
                abort(422, 'A reading already exists for this meter and date.');
            }

            $reading = MeterReading::create([
                'agency_id' => $meter->agency_id,
                'meter_id' => $meter->id,
                'reading_date' => $date,
                'reading_value' => $value,
                'recorded_by' => $this->actor()?->id,
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit()->log('utilities.reading_create', $reading, [
                'meter_id' => $meter->id,
                'value' => $value,
                'consumption_since_last' => round($value - $prevValue, 2),
            ]);

            return $reading;
        });
    }

    public function listReadings(UtilityMeter $meter, array $filters = [])
    {
        $this->ensureMeterAccess($meter);

        $query = $meter->readings()->with('recordedBy:id,name')->orderByDesc('reading_date');

        if (! empty($filters['from'])) $query->whereDate('reading_date', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('reading_date', '<=', $filters['to']);

        return $query->paginate($filters['per_page'] ?? 30);
    }

    /**
     * Consumption between two readings.
     */
    public function consumption(UtilityMeter $meter, string $fromDate, string $toDate): array
    {
        $this->ensureMeterAccess($meter);

        $prev = $meter->readings()->whereDate('reading_date', '<', $fromDate)
            ->orderByDesc('reading_date')->first();
        $prevValue = $prev ? (float) $prev->reading_value : (float) $meter->opening_reading;

        $current = $meter->readings()->whereDate('reading_date', '<=', $toDate)
            ->orderByDesc('reading_date')->first();

        if (! $current) {
            abort(422, 'No readings found for the requested period.');
        }

        $consumption = round((float) $current->reading_value - $prevValue, 2);

        return [
            'meter_id' => $meter->id,
            'previous_reading' => $prevValue,
            'previous_date' => $prev?->reading_date?->toDateString(),
            'current_reading' => (float) $current->reading_value,
            'current_date' => $current->reading_date->toDateString(),
            'consumption' => $consumption,
            'unit' => $meter->unit_of_measure,
        ];
    }

    public function ensureMeterAccess(UtilityMeter $meter): void
    {
        $this->ensureAgencyAccess($meter->agency_id);

        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            abort(404);
        }

        if ($actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            // Tenant sees meters for their unit(s) via lease.
            $unitIds = \App\Domains\Leasing\Models\Lease::whereIn('tenant_id', $tenantIds ?? [])
                ->whereIn('status', ['active'])->pluck('unit_id')->all();
            if (! in_array($meter->unit_id, $unitIds, true)) {
                abort(404);
            }
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds) && ! in_array($meter->property_id, $propertyIds, true)) {
                abort(404);
            }
        }
    }

    private function applyMeterScope($query): void
    {
        $actor = $this->actor();
        if (! $actor) return;

        if ($actor->hasRole('technician')) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($actor->hasRole('tenant')) {
            $tenantIds = TenantAccess::accessibleTenantIds($actor);
            $unitIds = \App\Domains\Leasing\Models\Lease::whereIn('tenant_id', $tenantIds ?? [])
                ->whereIn('status', ['active'])->pluck('unit_id')->all();
            $query->whereIn('unit_id', $unitIds);
            return;
        }

        if ($actor->hasRole('owner')) {
            $propertyIds = \App\Domains\Property\Services\PropertyAccess::accessiblePropertyIds($actor);
            if (is_array($propertyIds)) {
                $query->whereIn('property_id', $propertyIds);
            }
        }
    }
}
