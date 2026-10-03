<?php

namespace App\Domains\Billing\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToAgency, HasFactory;

    public const METHODS = ['cash', 'bank_transfer', 'online', 'card', 'other'];
    public const STATUSES = ['posted', 'reversed'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'lease_id', 'receipt_number', 'payment_date',
        'amount', 'method', 'reference', 'notes', 'currency', 'posted_by',
        'status', 'idempotency_key',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function postedBy() { return $this->belongsTo(User::class, 'posted_by'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }

    /** Sum of allocations; invariant: <= amount. */
    public function allocatedTotal(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    public function unallocated(): float
    {
        return max(0, round((float) $this->amount - $this->allocatedTotal(), 2));
    }
}
