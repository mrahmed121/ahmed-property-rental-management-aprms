<?php

namespace App\Domains\Property\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyDocument extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = ['deed', 'noc', 'floor_plan', 'photo', 'agreement', 'other'];

    /** Parent classes allowed as document owners. */
    public const ALLOWED_PARENTS = [
        'property' => Property::class,
        'building' => Building::class,
        'unit' => Unit::class,
    ];

    protected $fillable = [
        'agency_id', 'documentable_type', 'documentable_id', 'name', 'document_type',
        'file_path', 'mime_type', 'file_size', 'description', 'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Short parent key: property | building | unit. */
    public function parentKey(): ?string
    {
        return array_search($this->documentable_type, self::ALLOWED_PARENTS, true) ?: null;
    }
}
