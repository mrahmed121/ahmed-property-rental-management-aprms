<?php

namespace App\Domains\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'city', 'country',
        'logo_path', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function settings()
    {
        return $this->hasMany(Setting::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function properties()
    {
        return $this->hasMany(\App\Domains\Property\Models\Property::class);
    }

    public function buildings()
    {
        return $this->hasMany(\App\Domains\Property\Models\Building::class);
    }

    public function units()
    {
        return $this->hasMany(\App\Domains\Property\Models\Unit::class);
    }

    public function propertyDocuments()
    {
        return $this->hasMany(\App\Domains\Property\Models\PropertyDocument::class);
    }
}
