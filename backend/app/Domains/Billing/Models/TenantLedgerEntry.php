<?php

namespace App\Domains\Billing\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantLedgerEntry extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = [
        'invoice', 'late_fee', 'utility', 'payment',
        'allocation', 'adjustment', 'reversal',
    ];

    protected $fillable = [
        'agency_id', 'tenant_id', 'lease_id', 'entry_type',
        'reference_type', 'reference_id', 'debit', 'credit',
        'balance_after', 'entry_date', 'description', 'created_by',
    ];

    protected $casts = [
        'debit' => 'decimal:2', 'credit' => 'decimal:2',
        'balance_after' => 'decimal:2', 'entry_date' => 'date',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function reference() { return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id'); }
}
