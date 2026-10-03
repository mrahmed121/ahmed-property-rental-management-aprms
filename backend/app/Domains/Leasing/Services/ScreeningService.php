<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Leasing\Models\TenantApplication;
use App\Domains\Shared\Services\DomainService;
use Illuminate\Support\Facades\DB;

/**
 * ScreeningService — the KYC/screening foundation.
 *
 * This is an honest foundation: it tracks screening state, reviewer and
 * timestamps, and links supporting documents through the existing
 * polymorphic document system. It does NOT claim integration with any
 * external identity-verification provider.
 */
class ScreeningService extends DomainService
{
    /** Begin screening: under_review -> screening. */
    public function start(TenantApplication $application): TenantApplication
    {
        app(ApplicationService::class)->ensureApplicationAccess($application);

        if ($application->status !== 'under_review') {
            abort(422, 'Screening can only start for applications under review.');
        }

        DB::transaction(fn () => $application->update([
            'status' => 'screening',
            'screening_status' => 'in_progress',
        ]));

        $this->audit()->log('screening.started', $application);

        return $application->fresh();
    }

    /**
     * Record the screening decision.
     * $clear = true  => screening clear, application ready for approval.
     * $clear = false => flagged; stays in screening until rejected or re-cleared.
     */
    public function decide(TenantApplication $application, bool $clear, ?string $notes, ?string $kycStatus): TenantApplication
    {
        app(ApplicationService::class)->ensureApplicationAccess($application);

        if ($application->status !== 'screening' || $application->screening_status !== 'in_progress') {
            abort(422, 'No screening is in progress for this application.');
        }

        if ($kycStatus && ! in_array($kycStatus, TenantApplication::KYC_STATUSES, true)) {
            abort(422, 'Invalid KYC status.');
        }

        DB::transaction(fn () => $application->update([
            'screening_status' => $clear ? 'clear' : 'flagged',
            'screening_notes' => $notes,
            'screened_by' => $this->actor()?->id,
            'screened_at' => now(),
            'kyc_status' => $kycStatus ?? $application->kyc_status,
        ]));

        $this->audit()->log($clear ? 'screening.cleared' : 'screening.flagged', $application);

        // Propagate KYC to the tenant record for a coherent profile.
        if ($kycStatus) {
            $application->tenant->update(['kyc_status' => $kycStatus]);
        }

        return $application->fresh();
    }
}
