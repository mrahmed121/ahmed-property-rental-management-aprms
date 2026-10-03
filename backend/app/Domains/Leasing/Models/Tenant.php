<?php

namespace App\Domains\Leasing\Models;

use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const STATUSES = ['prospective', 'active', 'inactive'];
    public const KYC_STATUSES = ['pending', 'verified', 'rejected'];

    protected $fillable = [
        'agency_id', 'user_id', 'first_name', 'last_name', 'email', 'phone',
        'national_id', 'address', 'city', 'emergency_contact_name',
        'emergency_contact_phone', 'status', 'kyc_status', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(TenantApplication::class);
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease()
    {
        return $this->hasOne(Lease::class)->where('status', 'active');
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
