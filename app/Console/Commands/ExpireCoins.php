<?php

namespace App\Console\Commands;

use App\Services\CoinService;
use Illuminate\Console\Command;

/**
 * M2.1 — expires lapsed coin lots (FIFO) and writes the offsetting ledger
 * entries. Idempotent: only lots with remaining > 0 past expiry are touched.
 */
class ExpireCoins extends Command
{
    protected $signature = 'coins:expire';

    protected $description = 'Expire lapsed Loyalty Coin lots';

    public function handle(CoinService $coins): int
    {
        $expired = $coins->expireDue();

        $this->info("Expired {$expired} coins.");

        return self::SUCCESS;
    }
}
