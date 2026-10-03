<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Leasing\Models\Tenant;
use App\Domains\Property\Models\Building;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = [
        'open', 'triaged', 'assigned', 'quoted', 'approval_pending',
        'approved', 'in_progress', 'completed', 'verified', 'closed',
        'cancelled', 'rejected',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const CATEGORIES = [
        'plumbing', 'electrical', 'carpentry', 'painting',
        'appliance', 'hvac', 'general',
    ];

    /** Allowed transitions: current => [next, ...]. */
    public const TRANSITIONS = [
        'open' => ['triaged', 'cancelled'],
        'triaged' => ['assigned', 'cancelled'],
        'assigned' => ['quoted', 'in_progress', 'cancelled'],
        'quoted' => ['approval_pending', 'cancelled'],
        'approval_pending' => ['approved', 'rejected'],
        'approved' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['verified', 'in_progress'],
        'verified' => ['closed'],
        'closed' => [],
        'cancelled' => [],
        'rejected' => ['quoted'],
    ];

    /** SLA targets in hours by priority. */
    public const SLA_HOURS = [
        'low' => 168,      // 7 days
        'normal' => 72,    // 3 days
        'high' => 24,      // 1 day
        'urgent' => 4,     // 4 hours
    ];

    protected $fillable = [
        'agency_id', 'property_id', 'building_id', 'unit_id', 'tenant_id',
        'reported_by', 'assigned_to', 'ticket_number', 'category',
        'description', 'priority', 'status', 'sla_due_at',
        'completed_at', 'closed_at', 'notes',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function property() { return $this->belongsTo(Property::class); }
    public function building() { return $this->belongsTo(Building::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function reportedBy() { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function quotes() { return $this->hasMany(MaintenanceQuote::class, 'ticket_id'); }
    public function workLogs() { return $this->hasMany(MaintenanceWorkLog::class, 'ticket_id'); }
    public function verification() { return $this->hasOne(MaintenanceVerification::class, 'ticket_id'); }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isBreached(): bool
    {
        return $this->sla_due_at
            && now()->greaterThan($this->sla_due_at)
            && ! in_array($this->status, ['closed', 'cancelled', 'verified'], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['closed', 'cancelled'], true);
    }
}
