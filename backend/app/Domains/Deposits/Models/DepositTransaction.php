<?php

namespace App\Domains\Deposits\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositTransaction extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = [
        'received', 'increase', 'adjustment',
        'deduction', 'refund', 'applied',
    ];

    protected $fillable = [
        'agency_id', 'deposit_id', 'type', 'amount', 'balance_after',
        'reason', 'reference_type', 'reference_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function deposit() { return $this->belongsTo(Deposit::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function reference() { return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id'); }
}
