<?php

namespace App\Domains\Utilities\Models;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UtilityMeter extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = ['electricity', 'gas', 'water', 'other'];
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'agency_id', 'property_id', 'building_id', 'unit_id',
        'meter_number', 'utility_type', 'unit_of_measure',
        'status', 'installation_date', 'opening_reading', 'notes',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'opening_reading' => 'decimal:2',
    ];

    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function readings() { return $this->hasMany(MeterReading::class, 'meter_id'); }
    public function bills() { return $this->hasMany(UtilityBill::class, 'meter_id'); }

    public function latestReading(): ?MeterReading
    {
        return $this->readings()->orderByDesc('reading_date')->orderByDesc('id')->first();
    }
}
