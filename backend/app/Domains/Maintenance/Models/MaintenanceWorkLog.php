<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceWorkLog extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'ticket_id', 'technician_id',
        'started_at', 'completed_at', 'notes',
        'parts_materials', 'labor_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ticket() { return $this->belongsTo(MaintenanceTicket::class, 'ticket_id'); }
    public function technician() { return $this->belongsTo(User::class, 'technician_id'); }
}
