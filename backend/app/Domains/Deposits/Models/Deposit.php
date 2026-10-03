<?php

namespace App\Domains\Deposits\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['required', 'held', 'partially_released', 'settled', 'closed'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'lease_id', 'unit_id', 'property_id',
        'deposit_amount', 'held_amount', 'status', 'received_date',
        'release_date', 'currency', 'reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'held_amount' => 'decimal:2',
        'received_date' => 'date',
        'release_date' => 'date',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function transactions() { return $this->hasMany(DepositTransaction::class); }
    public function deductions() { return $this->hasMany(DepositDeduction::class); }
    public function settlement() { return $this->hasOne(DepositSettlement::class); }

    public function isSettled(): bool
    {
        return in_array($this->status, ['settled', 'closed'], true);
    }
}
