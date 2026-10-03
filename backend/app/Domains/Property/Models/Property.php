<?php

namespace App\Domains\Property\Models;

use App\Domains\Shared\Models\Agency;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const TYPES = ['residential', 'commercial', 'mixed-use'];
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'agency_id', 'owner_id', 'name', 'property_type', 'address', 'city',
        'postal_code', 'description', 'status', 'notes',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    /** A property is archived when soft-deleted. */
    public function isArchived(): bool
    {
        return $this->trashed();
    }
}
