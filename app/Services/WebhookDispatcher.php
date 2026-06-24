<?php

namespace App\Services;

use App\Jobs\SendWebhookJob;
use App\Models\WebhookSubscription;

/** Fans an event out to every matching active webhook subscription (M1.7). */
class WebhookDispatcher
{
    /** @param  array<string, mixed>  $payload */
    public function dispatch(string $event, array $payload, ?int $storeId = null): void
    {
        foreach (WebhookSubscription::listeningFor($event, $storeId) as $subscription) {
            SendWebhookJob::dispatch($subscription->url, $subscription->secret, $event, $payload);
        }
    }
}
