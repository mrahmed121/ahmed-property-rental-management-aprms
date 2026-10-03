<?php

namespace App\Domains\Statements\Models;

use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\Unit;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementLine extends Model
{
    use BelongsToAgency, HasFactory;

    public const TYPES = [
        'income', 'management_fee', 'expense', 'maintenance', 'utility', 'adjustment',
    ];

    protected $fillable = [
        'agency_id', 'owner_statement_id', 'line_type', 'source_type',
        'source_id', 'property_id', 'unit_id', 'description',
        'line_date', 'amount', 'reference',
    ];

    protected $casts = [
        'line_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function statement() { return $this->belongsTo(OwnerStatement::class, 'owner_statement_id'); }
    public function property() { return $this->belongsTo(Property::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function source() { return $this->morphTo(__FUNCTION__, 'source_type', 'source_id'); }
}
