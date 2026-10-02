<?php

namespace App\Domains\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'agency_id', 'name', 'email', 'phone', 'password', 'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // --- JWT ---
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'agency_id' => $this->agency_id,
            'roles' => $this->roles()->pluck('roles.slug')->all(),
        ];
    }

    // --- Relations ---
    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    // --- Authorization helpers ---
    /** All permission slugs granted via the user's roles (agency-aware). */
    public function permissionSlugs(): array
    {
        if (! $this->relationLoaded('roles.permissions')) {
            $this->load('roles.permissions');
        }

        return $this->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    public function hasRole(string $slug): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return is_null($this->agency_id) && $this->hasRole('super-admin');
    }

    /** A user may only act inside their own agency (Super Admin is global). */
    public function canAccessAgency(?int $agencyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->agency_id !== null && $this->agency_id === $agencyId;
    }
}
