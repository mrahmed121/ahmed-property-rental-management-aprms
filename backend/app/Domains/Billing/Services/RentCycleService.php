<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * RentCycleService — P4 contract.
 *
 * Will own: monthly invoice generation (idempotent), mid-month proration,
 * period handling. Reads late-fee rules from SettingService.
 */
class RentCycleService extends DomainService
{
    // P4: public function generateForPeriod(...), prorate(...), ...
}
