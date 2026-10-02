<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Models\AuditLog;
use App\Domains\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * AuditService — the single write path for the audit trail.
 * P1 records: auth.login, auth.logout, auth.failed_login, users.*, roles.*,
 * settings.update. Financial/security modules (P4+) will log through here too.
 */
class AuditService
{
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $extraContext = null,
        ?User $actor = null,
    ): AuditLog {
        $actor = $actor ?? Auth::user();

        return AuditLog::create([
            'agency_id' => $actor?->agency_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => array_filter([
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
                ...($extraContext ?? []),
            ]),
        ]);
    }

    /** Convenience wrapper capturing before/after snapshots of a model. */
    public function logModelChange(
        string $action,
        Model $entity,
        ?array $oldValues = null,
        ?User $actor = null,
    ): AuditLog {
        return $this->log(
            action: $action,
            entity: $entity,
            oldValues: $oldValues,
            newValues: $entity->fresh()?->toArray(),
            actor: $actor,
        );
    }
}
