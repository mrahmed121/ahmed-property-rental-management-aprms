<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * AllocationService — P4 contract.
 *
 * Will own the payment waterfall: late fees → utilities → current rent →
 * oldest arrears. Reversals create linked reversing entries, never deletes.
 */
class AllocationService extends DomainService
{
    // P4: public function allocate(...), reverse(...), ...
}
