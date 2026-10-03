<?php

namespace App\Domains\Property\Models;

use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'agency_id', 'property_id', 'name', 'floors', 'description', 'status', 'notes',
    ];

    protected $casts = [
        'floors' => 'integer',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public function isArchived(): bool
    {
        return $this->trashed();
    }
}
