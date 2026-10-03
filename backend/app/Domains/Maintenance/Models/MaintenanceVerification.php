<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceVerification extends Model
{
    use BelongsToAgency, HasFactory;

    public const RESULTS = ['passed', 'failed'];

    protected $fillable = [
        'agency_id', 'ticket_id', 'verified_by',
        'verified_at', 'notes', 'result',
    ];

    protected $casts = ['verified_at' => 'datetime'];

    public function ticket() { return $this->belongsTo(MaintenanceTicket::class, 'ticket_id'); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
}
