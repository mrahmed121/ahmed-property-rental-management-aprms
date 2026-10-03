<?php

namespace App\Domains\Billing\Models;

use App\Domains\Leasing\Models\Lease;
use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LateFee extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['accrued', 'waived', 'paid'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'lease_id', 'invoice_id',
        'amount', 'status', 'accrued_date', 'rule_snapshot',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'accrued_date' => 'date',
        'rule_snapshot' => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function lease() { return $this->belongsTo(Lease::class); }
    public function invoice() { return $this->belongsTo(RentInvoice::class, 'invoice_id'); }

    public function allocations()
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function outstanding(): float
    {
        if ($this->status !== 'accrued') return 0;
        $allocated = (float) $this->allocations()->sum('amount');
        return max(0, round((float) $this->amount - $allocated, 2));
    }
}
