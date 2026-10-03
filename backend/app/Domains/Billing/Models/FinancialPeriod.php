<?php

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialPeriod extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'period', 'status', 'locked_at', 'locked_by',
    ];

    protected $casts = ['locked_at' => 'datetime'];

    public function lockedBy() { return $this->belongsTo(User::class, 'locked_by'); }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public static function periodFor(\DateTimeInterface $date): string
    {
        return $date->format('Y-m');
    }
}
