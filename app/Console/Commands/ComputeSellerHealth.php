<?php

namespace App\Console\Commands;

use App\Enums\SubOrderStatus;
use App\Models\SellerHealth;
use App\Models\Store;
use Illuminate\Console\Command;

/**
 * Seller account-health rollup (M1.4): cancel / return / defect rates from the
 * store's recent sub-orders, in integer basis points. Feeds the seller
 * scorecard and (later) ranking/feature gates.
 */
class ComputeSellerHealth extends Command
{
    protected $signature = 'seller:compute-health {--days=90}';

    protected $description = "Recompute every store's health scorecard";

    public function handle(): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $updated = 0;

        foreach (Store::query()->cursor() as $store) {
            $statuses = $store->subOrders()
                ->where('created_at', '>=', $since)
                ->pluck('status');

            $total = $statuses->count();

            if ($total === 0) {
                continue;
            }

            $cancelled = $statuses->filter(fn ($s) => $s === SubOrderStatus::Cancelled)->count();
            $returned = $statuses->filter(fn ($s) => in_array($s, [SubOrderStatus::Returned, SubOrderStatus::Refunded], true))->count();

            SellerHealth::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'orders_counted' => $total,
                    'cancel_rate_bp' => intdiv($cancelled * 10000, $total),
                    'return_rate_bp' => intdiv($returned * 10000, $total),
                    'defect_rate_bp' => intdiv(($cancelled + $returned) * 10000, $total),
                    'computed_at' => now(),
                ],
            );

            $updated++;
        }

        $this->info("Seller health scorecards updated: {$updated}");

        return self::SUCCESS;
    }
}
