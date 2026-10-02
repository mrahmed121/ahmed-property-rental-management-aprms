<?php

namespace App\Domains\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    /** Immutable trail — no updated_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'agency_id', 'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'context',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
