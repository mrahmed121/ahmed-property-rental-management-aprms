<?php

namespace App\Domains\Deposits\Models;

use App\Domains\Leasing\Models\MoveOutInspection;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositDeduction extends Model
{
    use BelongsToAgency, HasFactory;

    public const CATEGORIES = ['damage', 'cleaning', 'unpaid_rent', 'other'];
    public const ASSESSMENTS = ['wear', 'damage'];
    public const STATUSES = ['proposed', 'approved', 'rejected'];

    protected $fillable = [
        'agency_id', 'deposit_id', 'settlement_id', 'inspection_id',
        'category', 'assessment', 'description', 'amount', 'status',
        'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function deposit() { return $this->belongsTo(Deposit::class); }
    public function settlement() { return $this->belongsTo(DepositSettlement::class); }
    public function inspection() { return $this->belongsTo(MoveOutInspection::class, 'inspection_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isChargeable(): bool
    {
        return $this->assessment === 'damage' && $this->status === 'approved';
    }
}
