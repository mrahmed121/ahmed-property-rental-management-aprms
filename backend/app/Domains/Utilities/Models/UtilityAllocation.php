<?php

namespace App\Domains\Utilities\Models;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UtilityAllocation extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = [
        'metered', 'equal_split', 'area_based', 'custom', 'vacant_owner',
    ];

    protected $fillable = [
        'agency_id', 'utility_bill_id', 'unit_id', 'tenant_id',
        'allocation_type', 'consumption_share', 'amount', 'notes',
    ];

    protected $casts = [
        'consumption_share' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function bill() { return $this->belongsTo(UtilityBill::class, 'utility_bill_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function isOwnerAbsorbed(): bool
    {
        return $this->allocation_type === 'vacant_owner';
    }
}
