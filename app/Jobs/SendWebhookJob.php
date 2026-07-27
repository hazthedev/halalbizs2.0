<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Delivers one signed webhook (M1.7). The body is HMAC-SHA256 signed with the
 * subscription secret (X-Webhook-Signature) so receivers can verify authenticity.
 */
class SendWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** M4: exponential backoff instead of an immediate hammer-retry. */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    /** Set once at dispatch and serialized with the job, so it survives retries. */
    public string $fallbackDeliveryId;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public int $subscriptionId,
        public string $url,
        public string $secret,
        public string $event,
        public array $payload,
        public ?string $idempotencyKey = null,
    ) {
        $this->fallbackDeliveryId = (string) Str::uuid();
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        // M5: re-check at SEND time, not only whenever a subscription is
        // created — DNS can be re-pointed after the fact, and this is the
        // only place that actually makes an outbound network call.
        if (! WebhookSubscription::isUrlSafe($this->url)) {
            Log::error('Webhook delivery refused — URL failed the SSRF safety check.', [
                'subscription_id' => $this->subscriptionId,
                'event' => $this->event,
                'url' => $this->url,
            ]);

            // Not a transient condition — retrying won't make a private/
            // metadata host become a public one, so fail immediately rather
            // than burn through the retry budget.
            $this->fail(new \RuntimeException('Webhook URL failed the SSRF safety check.'));

            return;
        }

        $body = json_encode(['event' => $this->event, 'data' => $this->payload], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $response = Http::withHeaders([
            'X-Webhook-Event' => $this->event,
            'X-Webhook-Signature' => $signature,
            // Stable across OUR queue retries (a fresh uuid per attempt would
            // make every retry look like a new event to the receiver); the
            // logical dedupe key when we have one, else one id per job
            // instance via the queued constructor property.
            'X-Webhook-Id' => $this->idempotencyKey ?? $this->fallbackDeliveryId,
        ])->timeout(10)->withBody($body, 'application/json')->post($this->url);

        // Non-2xx throws, so the queue's tries/backoff actually retries it —
        // previously a failing endpoint returned "success" from the job's
        // point of view since nothing checked the response.
        $response->throw();

        // M4: claim-on-success. Recording the dedupe row only now (never
        // before this point) means a delivery that fails every retry leaves
        // no row behind, so the event can still be redelivered later instead
        // of being silently and permanently lost.
        if ($this->idempotencyKey !== null) {
            WebhookDelivery::query()->insertOrIgnore([
                'webhook_subscription_id' => $this->subscriptionId,
                'dedupe_key' => $this->idempotencyKey,
                'created_at' => now(),
            ]);
        }
    }

    /** M4: every retry is exhausted — log loudly, this delivery is now lost until redelivered. */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Webhook delivery permanently failed after all retries.', [
            'subscription_id' => $this->subscriptionId,
            'event' => $this->event,
            'url' => $this->url,
            'error' => $exception?->getMessage(),
        ]);
    }
}
