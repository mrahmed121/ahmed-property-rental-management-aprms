<?php

namespace App\Domains\Statements\Models;

use App\Domains\Shared\Models\User;
use App\Domains\Shared\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementAdjustment extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'owner_statement_id', 'amount', 'reason', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function statement() { return $this->belongsTo(OwnerStatement::class, 'owner_statement_id'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
