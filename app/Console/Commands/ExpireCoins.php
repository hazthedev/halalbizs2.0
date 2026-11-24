<?php

namespace App\Console\Commands;

use App\Services\CoinService;
use Illuminate\Console\Command;

/**
 * M2.1 — expires lapsed coin lots (FIFO) and writes the offsetting ledger
 * entries. Idempotent: only lots with remaining > 0 past expiry are touched.
 * BIZ-04: then reminds buyers once per lot, 30 days before it lapses.
 */
class ExpireCoins extends Command
{
    protected $signature = 'coins:expire';

    protected $description = 'Expire lapsed Loyalty Coin lots';

    public function handle(CoinService $coins): int
    {
        $expired = $coins->expireDue();

        $reminded = $coins->remindExpiring();

        $this->info("Expired {$expired} coins. Reminded {$reminded} buyers.");

        return self::SUCCESS;
    }
}
