<?php

namespace App\Domains\Billing\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * DepositService — P5 contract.
 *
 * Will own: deposit collection (liability, never revenue), settlement
 * worksheet with inspection deductions, refunds and arrears adjustments.
 */
class DepositService extends DomainService
{
    // P5: public function collect(...), settle(...), refund(...), ...
}
