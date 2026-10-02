<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * DunningService — P4 contract.
 *
 * Will own: day 3/7/15/30 reminder sequence, late-fee accrual with caps,
 * escalation + legal-notice workflow. Every step writes to the reminder log.
 */
class DunningService extends DomainService
{
    // P4: public function processOverdue(...), accrueLateFees(...), ...
}
