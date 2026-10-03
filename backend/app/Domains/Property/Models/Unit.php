<?php

namespace App\Domains\Property\Models;

use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const TYPES = ['apartment', 'office', 'shop', 'room', 'studio', 'warehouse', 'other'];
    public const STATUSES = ['vacant', 'occupied', 'reserved', 'maintenance', 'inactive'];

    protected $fillable = [
        'agency_id', 'building_id', 'property_id', 'unit_number', 'floor', 'unit_type',
        'area_sqft', 'bedrooms', 'bathrooms', 'status', 'market_rent', 'notes',
    ];

    protected $casts = [
        'floor' => 'integer',
        'area_sqft' => 'decimal:2',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'market_rent' => 'decimal:2',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public function isArchived(): bool
    {
        return $this->trashed();
    }

    public function isVacant(): bool
    {
        return $this->status === 'vacant';
    }
}
