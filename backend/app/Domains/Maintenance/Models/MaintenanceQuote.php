<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceQuote extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected'];
    public const ATTRIBUTIONS = ['owner', 'tenant'];

    protected $fillable = [
        'agency_id', 'ticket_id', 'vendor_id', 'provider',
        'labor_cost', 'materials_cost', 'total', 'notes',
        'attribution', 'attribution_reason',
        'status', 'created_by', 'approved_by', 'decided_at',
    ];

    protected $casts = [
        'labor_cost' => 'decimal:2',
        'materials_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public function ticket() { return $this->belongsTo(MaintenanceTicket::class, 'ticket_id'); }
    public function vendor() { return $this->belongsTo(MaintenanceVendor::class, 'vendor_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
