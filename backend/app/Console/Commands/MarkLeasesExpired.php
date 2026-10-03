<?php

namespace App\Console\Commands;

use App\Domains\Leasing\Services\LeaseService;
use Illuminate\Console\Command;

class MarkLeasesExpired extends Command
{
    protected $signature = 'leases:mark-expired';
    protected $description = 'Transition past-due active leases to expired and restore unit vacancy.';

    public function handle(LeaseService $leases): int
    {
        $count = $leases->markExpired();
        $this->info("Marked {$count} lease(s) as expired.");

        return self::SUCCESS;
    }
}
