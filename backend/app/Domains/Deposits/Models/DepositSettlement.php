<?php

namespace App\Domains\Deposits\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\MoveOutInspection;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositSettlement extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['draft', 'finalized'];

    protected $fillable = [
        'agency_id', 'deposit_id', 'tenant_id', 'lease_id', 'inspection_id',
        'gross_deposit', 'total_deductions', 'applied_to_balance',
        'refund_amount', 'status', 'finalized_at', 'approved_by', 'notes',
    ];

    protected $casts = [
        'gross_deposit' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'applied_to_balance' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function deposit() { return $this->belongsTo(Deposit::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function inspection() { return $this->belongsTo(MoveOutInspection::class, 'inspection_id'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function deductions() { return $this->hasMany(DepositDeduction::class, 'settlement_id'); }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * Deterministic settlement math:
     *   refund = gross − deductions − applied_to_balance
     * Never negative; deductions + applied can never exceed gross.
     */
    public static function calculate(float $gross, float $deductions, float $applied): array
    {
        $gross = round($gross, 2);
        $deductions = round($deductions, 2);
        $applied = round($applied, 2);

        if ($deductions < 0 || $applied < 0) {
            abort(422, 'Deductions and applied amounts cannot be negative.');
        }
        if (round($deductions + $applied, 2) > $gross) {
            abort(422, 'Deductions plus applied amount cannot exceed the held deposit.');
        }

        return [
            'gross_deposit' => $gross,
            'total_deductions' => $deductions,
            'applied_to_balance' => $applied,
            'refund_amount' => round($gross - $deductions - $applied, 2),
        ];
    }
}
