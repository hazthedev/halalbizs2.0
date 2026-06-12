<?php

namespace App\Console\Commands;

use App\Enums\BoostStatus;
use App\Models\ProductBoost;
use Illuminate\Console\Command;

/**
 * Hourly. Active boosts whose paid window has ended flip to expired so the
 * sponsored placements stop. No money moves — the fee was charged up-front.
 */
class ExpireBoosts extends Command
{
    protected $signature = 'boosts:expire';

    protected $description = 'Mark active product boosts whose window has ended as expired';

    public function handle(): int
    {
        $count = ProductBoost::query()
            ->where('status', BoostStatus::Active)
            ->where('ends_at', '<=', now())
            ->update(['status' => BoostStatus::Expired->value, 'updated_at' => now()]);

        $this->info("Expired {$count} boost(s).");

        return self::SUCCESS;
    }
}
