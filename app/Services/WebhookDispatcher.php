<?php

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

/** Fans an event out to every matching active webhook subscription (M1.7). */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  string|null  $dedupeKey  A stable key for this LOGICAL event (e.g.
     *                                   "order.paid:MP2606…"). When given, each
     *                                   subscription is delivered at most once for
     *                                   that key — a re-fired event is a no-op.
     */
    public function dispatch(string $event, array $payload, ?int $storeId = null, ?string $dedupeKey = null): void
    {
        foreach (WebhookSubscription::listeningFor($event, $storeId) as $subscription) {
            // Atomic claim on the unique (subscription, key) index — 0 rows
            // inserted means this event was already delivered to this subscriber.
            if ($dedupeKey !== null) {
                $claimed = WebhookDelivery::query()->insertOrIgnore([
                    'webhook_subscription_id' => $subscription->id,
                    'dedupe_key' => $dedupeKey,
                    'created_at' => now(),
                ]);

                if ($claimed === 0) {
                    continue;
                }
            }

            SendWebhookJob::dispatch($subscription->url, $subscription->secret, $event, $payload, $dedupeKey);
        }
    }
}
