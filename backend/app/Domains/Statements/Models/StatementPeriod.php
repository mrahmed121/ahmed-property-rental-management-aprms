<?php

namespace App\Domains\Statements\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementPeriod extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['open', 'review', 'approved', 'finalized', 'locked'];

    public const TRANSITIONS = [
        'open' => ['review'],
        'review' => ['approved', 'open'],
        'approved' => ['finalized'],
        'finalized' => ['locked'],
        'locked' => [],
    ];

    protected $fillable = [
        'agency_id', 'start_date', 'end_date', 'status',
        'locked_by', 'locked_at', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'locked_at' => 'datetime',
    ];

    public function statements() { return $this->hasMany(OwnerStatement::class, 'statement_period_id'); }
    public function lockedBy() { return $this->belongsTo(User::class, 'locked_by'); }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
