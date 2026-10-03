<?php

namespace App\Domains\Leasing\Models;

use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lease extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'renewed', 'terminated', 'expired'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'unit_id', 'property_id', 'building_id',
        'application_id', 'previous_lease_id', 'lease_number', 'start_date',
        'end_date', 'monthly_rent', 'deposit_amount', 'status', 'terms',
        'notes', 'created_by', 'activated_at', 'terminated_at',
        'termination_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_rent' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'activated_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function application()
    {
        return $this->belongsTo(TenantApplication::class, 'application_id');
    }

    public function previousLease()
    {
        return $this->belongsTo(Lease::class, 'previous_lease_id');
    }

    public function successorLease()
    {
        return $this->hasOne(Lease::class, 'previous_lease_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inspection()
    {
        return $this->hasOne(MoveOutInspection::class);
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** True when the lease period has ended but status hasn't been updated. */
    public function isPastEndDate(): bool
    {
        return $this->end_date->isPast();
    }
}
