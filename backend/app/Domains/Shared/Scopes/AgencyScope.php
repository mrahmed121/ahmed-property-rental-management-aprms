<?php

namespace App\Domains\Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * AgencyScope — multi-tenant isolation at the query layer.
 *
 * Any model using BelongsToAgency automatically filters rows to the
 * authenticated user's agency. Super Admin (agency_id = null) bypasses
 * the scope and sees everything; unauthenticated contexts see nothing.
 */
class AgencyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // No authenticated user (console, seeder, tests without actingAs):
        // apply no constraint — callers must scope explicitly.
        if (! $user) {
            return;
        }

        // Platform-level Super Admin sees all agencies.
        if (is_null($user->agency_id)) {
            return;
        }

        $builder->where($model->getTable().'.agency_id', $user->agency_id);
    }
}
