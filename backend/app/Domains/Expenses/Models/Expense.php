<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Maintenance\Models\MaintenanceVendor;
use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToAgency, HasFactory;

    public const CATEGORIES = [
        'maintenance', 'utilities', 'repairs', 'cleaning', 'security',
        'tax_fee', 'insurance', 'management', 'supplies', 'other',
    ];

    public const STATUSES = [
        'draft', 'submitted', 'approved', 'rejected', 'posted', 'reversed',
    ];

    /** Allowed transitions. */
    public const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['approved', 'rejected'],
        'approved' => ['posted'],
        'rejected' => ['draft'],
        'posted' => ['reversed'],
        'reversed' => [],
    ];

    protected $fillable = [
        'agency_id', 'property_id', 'building_id', 'unit_id', 'vendor_id',
        'expense_number', 'category', 'description', 'expense_date',
        'amount', 'currency', 'status', 'submitted_by', 'approved_by',
        'approved_at', 'posted_at', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function vendor() { return $this->belongsTo(MaintenanceVendor::class, 'vendor_id'); }
    public function submittedBy() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['posted', 'reversed'], true);
    }
}
