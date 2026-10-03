<?php

namespace App\Domains\Utilities\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    use BelongsToAgency, HasFactory;

    public const SOURCES = ['manual', 'import', 'estimate'];

    protected $fillable = [
        'agency_id', 'meter_id', 'reading_date', 'reading_value',
        'recorded_by', 'source', 'notes',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'reading_value' => 'decimal:2',
    ];

    public function meter() { return $this->belongsTo(UtilityMeter::class, 'meter_id'); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
