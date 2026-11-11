<?php

namespace App\Console\Commands;

use App\Services\LedgerService;
use Illuminate\Console\Command;

/** Moves seller money off hold once its return window has closed (idempotent). */
class ReleaseLedgerHolds extends Command
{
    protected $signature = 'ledger:release-holds';

    protected $description = 'Release pending seller ledger rows whose return window has closed';

    public function handle(LedgerService $ledger): int
    {
        $this->info('Released '.$ledger->releaseDue().' ledger row(s).');

        return self::SUCCESS;
    }
}
