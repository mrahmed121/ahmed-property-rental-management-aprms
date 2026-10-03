<?php

namespace App\Domains\Billing\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentInvoice extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['issued', 'partially_paid', 'paid', 'overdue', 'void'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'lease_id', 'unit_id', 'property_id', 'building_id',
        'invoice_number', 'period_start', 'period_end', 'issue_date', 'due_date',
        'base_rent', 'late_fee', 'utilities', 'other_charges', 'total', 'paid_amount',
        'status', 'currency', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date',
        'issue_date' => 'date', 'due_date' => 'date',
        'base_rent' => 'decimal:2', 'late_fee' => 'decimal:2',
        'utilities' => 'decimal:2', 'other_charges' => 'decimal:2',
        'total' => 'decimal:2', 'paid_amount' => 'decimal:2',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function lateFeeRecord() { return $this->hasOne(LateFee::class, 'invoice_id'); }
    public function dunningReminders() { return $this->hasMany(DunningReminder::class, 'invoice_id'); }

    public function allocations()
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    /** Outstanding = total - paid_amount, never negative. */
    public function outstanding(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid'], true)
            && $this->due_date->isPast()
            && $this->outstanding() > 0;
    }
}
