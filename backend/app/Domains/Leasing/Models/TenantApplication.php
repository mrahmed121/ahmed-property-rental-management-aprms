<?php

namespace App\Domains\Leasing\Models;

use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantApplication extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['draft', 'submitted', 'under_review', 'screening', 'approved', 'rejected'];
    public const SCREENING_STATUSES = ['not_started', 'in_progress', 'clear', 'flagged'];
    public const KYC_STATUSES = ['pending', 'verified', 'rejected'];

    /** Allowed status transitions (workflow guard). */
    public const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'rejected'],
        'under_review' => ['screening', 'rejected'],
        'screening' => ['approved', 'rejected'],
        'approved' => [],
        'rejected' => [],
    ];

    protected $fillable = [
        'agency_id', 'tenant_id', 'property_id', 'unit_id', 'status',
        'screening_status', 'screening_notes', 'screened_by', 'screened_at',
        'kyc_status', 'notes', 'reviewed_by', 'reviewed_at', 'decision_notes',
    ];

    protected $casts = [
        'screened_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function screenedBy()
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function lease()
    {
        return $this->hasOne(Lease::class, 'application_id');
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
