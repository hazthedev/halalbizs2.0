<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Credits a seller's ledger so Playwright can exercise balance-gated
 * features (boosts, payouts) without walking a full order to completion.
 */
class E2eLedgerCredit extends Command
{
    protected $signature = 'e2e:ledger-credit {email} {sen=500000}';

    protected $description = 'Add an available ledger credit to a seller store (local only)';

    public function handle(LedgerService $ledger): int
    {
        if (! app()->environment('local')) {
            return self::FAILURE;
        }

        $store = User::where('email', $this->argument('email'))->firstOrFail()->store;

        if ($store === null) {
            $this->error('That user has no store.');

            return self::FAILURE;
        }

        $ledger->adjustment($store, (int) $this->argument('sen'), 'E2E test credit');

        $this->info("Credited {$this->argument('sen')} sen to {$store->name}. Balance: {$store->availableBalanceSen()} sen.");

        return self::SUCCESS;
    }
}
