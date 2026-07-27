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
     *                                  "order.paid:MP2606…"). When given, each
     *                                  subscription is delivered at most once for
     *                                  that key — a re-fired event is a no-op.
     */
    public function dispatch(string $event, array $payload, ?int $storeId = null, ?string $dedupeKey = null): void
    {
        foreach (WebhookSubscription::listeningFor($event, $storeId) as $subscription) {
            // M4: dedupe is checked here but claimed ONLY on a successful
            // delivery (SendWebhookJob::handle()). Claiming here, before the
            // job even ran, meant a fully-failed delivery (seller endpoint
            // down for all retries) left the row in place forever and the
            // event was silently lost with no way to ever redeliver it.
            if ($dedupeKey !== null) {
                $alreadyDelivered = WebhookDelivery::query()
                    ->where('webhook_subscription_id', $subscription->id)
                    ->where('dedupe_key', $dedupeKey)
                    ->exists();

                if ($alreadyDelivered) {
                    continue;
                }
            }

            SendWebhookJob::dispatch($subscription->id, $subscription->url, $subscription->secret, $event, $payload, $dedupeKey);
        }
    }
}
