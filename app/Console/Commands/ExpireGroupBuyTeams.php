<?php

namespace App\Console\Commands;

use App\Services\GroupBuyService;
use Illuminate\Console\Command;

/**
 * M2.6 — closes group-buy teams that never reached their target before the
 * window lapsed. Unlocked teams are untouched (members keep their deal price).
 */
class ExpireGroupBuyTeams extends Command
{
    protected $signature = 'group-buy:expire';

    protected $description = 'Expire group-buy teams whose recruiting window has closed';

    public function handle(GroupBuyService $groupBuy): int
    {
        $count = $groupBuy->expireDueTeams();

        $this->info("Expired {$count} group-buy teams.");

        return self::SUCCESS;
    }
}
