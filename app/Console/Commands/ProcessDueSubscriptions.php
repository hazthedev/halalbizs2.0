<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * M2.8 — places orders for subscribe-and-save schedules that have come due.
 * Idempotent: each subscription advances its next_run_at under a row lock
 * before placing, so reruns and overlapping runs never double-order.
 */
class ProcessDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Place orders for due subscribe-and-save schedules';

    public function handle(SubscriptionService $subscriptions): int
    {
        $placed = $subscriptions->processDue();

        $this->info("Placed {$placed} subscription orders.");

        return self::SUCCESS;
    }
}
