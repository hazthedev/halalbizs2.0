<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (subscription, logical event) actually dispatched — the
 * idempotency guard that stops a re-fired event from double-delivering.
 */
class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['webhook_subscription_id', 'dedupe_key', 'created_at'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
