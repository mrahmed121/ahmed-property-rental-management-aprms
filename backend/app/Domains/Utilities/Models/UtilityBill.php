<?php

namespace App\Domains\Utilities\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UtilityBill extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['draft', 'finalized', 'reversed'];
    public const ALLOCATION_METHODS = ['metered', 'equal_split', 'area_based', 'custom'];

    protected $fillable = [
        'agency_id', 'meter_id', 'property_id', 'building_id', 'unit_id',
        'tenant_id', 'lease_id', 'bill_number', 'period_start', 'period_end',
        'previous_reading', 'current_reading', 'consumption',
        'rate', 'fixed_charge', 'tax_amount', 'total', 'currency',
        'status', 'allocation_method', 'created_by', 'finalized_by',
        'finalized_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'previous_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'consumption' => 'decimal:2',
        'rate' => 'decimal:4',
        'fixed_charge' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function meter() { return $this->belongsTo(UtilityMeter::class, 'meter_id'); }
    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function allocations() { return $this->hasMany(UtilityAllocation::class, 'utility_bill_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function finalizedBy() { return $this->belongsTo(User::class, 'finalized_by'); }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * Deterministic bill math: total = consumption × rate + fixed + tax.
     */
    public static function calculate(float $consumption, float $rate, float $fixed = 0, float $tax = 0): array
    {
        $consumption = round($consumption, 2);
        $rate = round($rate, 4);
        $fixed = round($fixed, 2);
        $tax = round($tax, 2);

        if ($consumption < 0) abort(422, 'Consumption cannot be negative.');
        if ($rate < 0 || $fixed < 0 || $tax < 0) abort(422, 'Rates and charges cannot be negative.');

        $variable = round($consumption * $rate, 2);

        return [
            'consumption' => $consumption,
            'variable_charge' => $variable,
            'fixed_charge' => $fixed,
            'tax_amount' => $tax,
            'total' => round($variable + $fixed + $tax, 2),
        ];
    }
}
