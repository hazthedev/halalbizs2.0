<?php

namespace App\Listeners;

use App\Events\ProductRestocked;
use App\Models\StockSubscription;
use App\Notifications\BackInStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/** On restock, alert everyone who subscribed to that variant, then clear them (one-shot). */
class NotifyBackInStockSubscribers implements ShouldQueue
{
    public function handle(ProductRestocked $event): void
    {
        StockSubscription::query()
            ->where('product_variant_id', $event->variant->id)
            ->whereNull('notified_at')
            ->with('user')
            ->get()
            ->each(function (StockSubscription $subscription) use ($event) {
                $subscription->user?->notify(new BackInStockNotification($event->variant));
                $subscription->delete();
            });
    }
}
