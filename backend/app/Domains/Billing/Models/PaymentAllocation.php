<?php

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'payment_id', 'allocatable_type', 'allocatable_id', 'amount', 'bucket',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment() { return $this->belongsTo(Payment::class); }
    public function allocatable() { return $this->morphTo(); }
}
