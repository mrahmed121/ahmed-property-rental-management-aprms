<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * DomainService — base class for all APRMS domain services.
 *
 * Rules:
 * - Controllers stay thin; business rules live here.
 * - Every service resolves the acting agency from the authenticated user.
 * - Cross-agency access is denied here, not in controllers.
 */
abstract class DomainService
{
    protected ?User $actor;

    public function __construct()
    {
        $this->actor = Auth::user();
    }

    /** The agency all operations are scoped to (null = Super Admin / system). */
    protected function agencyId(): ?int
    {
        return $this->actor?->agency_id;
    }

    /**
     * Guard: the actor may only touch records of their own agency.
     * Throws 403 otherwise.
     */
    protected function ensureAgencyAccess(?int $agencyId): void
    {
        $actor = $this->actor;

        if (! $actor || ! $actor->canAccessAgency($agencyId)) {
            abort(403, 'You do not have access to this agency\'s data.');
        }
    }

    protected function audit(): AuditService
    {
        return app(AuditService::class);
    }
}
