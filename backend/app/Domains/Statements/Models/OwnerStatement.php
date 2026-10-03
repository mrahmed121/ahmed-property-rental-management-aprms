<?php

namespace App\Domains\Statements\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerStatement extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['draft', 'review', 'approved', 'finalized'];

    public const TRANSITIONS = [
        'draft' => ['review'],
        'review' => ['approved', 'draft'],
        'approved' => ['finalized'],
        'finalized' => [],
    ];

    protected $fillable = [
        'agency_id', 'statement_period_id', 'owner_id', 'statement_number',
        'currency', 'status', 'gross_income', 'management_fee_percent',
        'management_fee', 'owner_expenses', 'owner_maintenance',
        'owner_utility_absorption', 'adjustments_total', 'net_amount',
        'generated_by', 'approved_by', 'approved_at',
        'finalized_by', 'finalized_at', 'notes',
    ];

    protected $casts = [
        'gross_income' => 'decimal:2',
        'management_fee_percent' => 'decimal:2',
        'management_fee' => 'decimal:2',
        'owner_expenses' => 'decimal:2',
        'owner_maintenance' => 'decimal:2',
        'owner_utility_absorption' => 'decimal:2',
        'adjustments_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function period() { return $this->belongsTo(StatementPeriod::class, 'statement_period_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function lines() { return $this->hasMany(StatementLine::class, 'owner_statement_id'); }
    public function adjustments() { return $this->hasMany(StatementAdjustment::class, 'owner_statement_id'); }
    public function generatedBy() { return $this->belongsTo(User::class, 'generated_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function finalizedBy() { return $this->belongsTo(User::class, 'finalized_by'); }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /**
     * Deterministic reconciliation:
     * net = income − fee − expenses − maintenance − utility + adjustments
     */
    public static function reconcile(
        float $income, float $fee, float $expenses,
        float $maintenance, float $utility, float $adjustments
    ): float {
        return round($income - $fee - $expenses - $maintenance - $utility + $adjustments, 2);
    }
}
