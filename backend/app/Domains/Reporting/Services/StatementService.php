<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Shared\Services\DomainService;

/**
 * StatementService — P7 contract.
 *
 * Will own: monthly owner statements (income − management fee − costs =
 * net payout), period locking, payouts. Re-running a closed month is blocked.
 */
class StatementService extends DomainService
{
    // P7: public function generateForPeriod(...), lock(...), payout(...), ...
}
