<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    protected $fillable = ['store_id', 'url', 'secret', 'events', 'is_active'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Active subscriptions wanting $event, scoped to the platform or a store. */
    public static function listeningFor(string $event, ?int $storeId = null): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('store_id')->when($storeId !== null, fn ($q) => $q->orWhere('store_id', $storeId)))
            ->get()
            ->filter(fn (self $subscription) => in_array($event, $subscription->events ?? [], true))
            ->values();
    }
}
