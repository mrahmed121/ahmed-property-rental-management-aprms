<?php

namespace App\Domains\Maintenance\Models;

use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceVendor extends Model
{
    use BelongsToAgency, HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'agency_id', 'name', 'contact_person', 'phone', 'email',
        'category', 'status', 'notes',
    ];

    public function quotes() { return $this->hasMany(MaintenanceQuote::class, 'vendor_id'); }
}
