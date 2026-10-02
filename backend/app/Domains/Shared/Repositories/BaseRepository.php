<?php

namespace App\Domains\Shared\Repositories;

use App\Domains\Shared\Scopes\AgencyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * BaseRepository — query-boundary helper demonstrating the repository pattern.
 * Agency isolation is enforced by the global AgencyScope; repositories add
 * explicit, intention-revealing query methods for their domain.
 */
abstract class BaseRepository
{
    abstract protected function modelClass(): string;

    protected function query(): Builder
    {
        /** @var Model $class */
        $class = $this->modelClass();

        return $class::query();
    }

    /** Bypass the agency scope — Super Admin / system use only. */
    protected function queryUnscoped(): Builder
    {
        /** @var Model $class */
        $class = $this->modelClass();

        return $class::withoutGlobalScope(AgencyScope::class);
    }
}
