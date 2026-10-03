<?php

namespace App\Domains\Leasing\Models;

use App\Domains\Property\Models\PropertyDocument;
use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoveOutInspection extends Model
{
    use BelongsToAgency, HasFactory;

    public const CONDITIONS = ['excellent', 'good', 'fair', 'poor', 'damaged'];
    public const REVIEW_STATUSES = ['pending', 'reviewed'];

    protected $fillable = [
        'agency_id', 'lease_id', 'inspection_date', 'condition', 'notes',
        'damage_observations', 'review_status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents()
    {
        return $this->morphMany(PropertyDocument::class, 'documentable');
    }
}
