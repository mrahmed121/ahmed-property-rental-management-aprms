<?php

namespace App\Domains\Leasing\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * LeaseService — P3 contract.
 *
 * Will own: draft → activate → renew → amend → terminate, overlap guards
 * (one ACTIVE lease per unit), move-in/move-out inspections.
 * No business logic yet — P1 foundation only establishes the service home.
 */
class LeaseService extends DomainService
{
    // P3: public function activate(...), renew(...), terminate(...), ...
}
