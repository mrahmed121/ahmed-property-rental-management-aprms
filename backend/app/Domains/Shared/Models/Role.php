<?php

namespace App\Domains\Shared\Models;

use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'name', 'slug', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** Flat list of permission slugs for this role. */
    public function permissionSlugs(): array
    {
        return $this->permissions()->pluck('permissions.slug')->all();
    }
}
