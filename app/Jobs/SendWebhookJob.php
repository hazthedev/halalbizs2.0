<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one signed webhook (M1.7). The body is HMAC-SHA256 signed with the
 * subscription secret (X-Webhook-Signature) so receivers can verify authenticity.
 */
class SendWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $url,
        public string $secret,
        public string $event,
        public array $payload,
        public ?string $idempotencyKey = null,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $body = json_encode(['event' => $this->event, 'data' => $this->payload], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $this->secret);

        Http::withHeaders(array_filter([
            'X-Webhook-Event' => $this->event,
            'X-Webhook-Signature' => $signature,
            // Lets receivers dedup independently even across our retries.
            'X-Webhook-Id' => $this->idempotencyKey,
        ]))->withBody($body, 'application/json')->post($this->url);
    }
}
