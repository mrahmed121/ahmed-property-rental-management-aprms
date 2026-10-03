<?php

namespace App\Domains\Billing\Models;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DunningReminder extends Model
{
    use BelongsToAgency, HasFactory;

    public const STAGES = ['day_3', 'day_7', 'day_15', 'day_30'];
    public const STATUSES = ['pending', 'sent', 'failed'];

    protected $fillable = [
        'agency_id', 'tenant_id', 'invoice_id', 'stage',
        'status', 'scheduled_at', 'sent_at', 'channel', 'message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime', 'sent_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function invoice() { return $this->belongsTo(RentInvoice::class, 'invoice_id'); }
}
