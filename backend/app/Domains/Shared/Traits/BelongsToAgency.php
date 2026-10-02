<?php

namespace App\Domains\Shared\Traits;

use App\Domains\Shared\Scopes\AgencyScope;
use Illuminate\Support\Facades\Auth;

/**
 * BelongsToAgency — attach to every agency-owned model.
 * Adds the AgencyScope global scope + the agency() relation + a helper
 * that auto-fills agency_id from the authenticated user on creation.
 */
trait BelongsToAgency
{
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new AgencyScope);

        static::creating(function ($model) {
            if (empty($model->agency_id) && Auth::check() && Auth::user()->agency_id) {
                $model->agency_id = Auth::user()->agency_id;
            }
        });
    }

    public function agency()
    {
        return $this->belongsTo(\App\Domains\Shared\Models\Agency::class);
    }

    /** Query without the agency constraint (Super Admin / system use). */
    public static function withoutAgencyScope()
    {
        return (new static)->newQueryWithoutScope(AgencyScope::class);
    }
}
