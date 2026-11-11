<?php

namespace App\Http\Resources;

use App\Models\Store;
use App\Services\LiveSessionService;
use App\Services\StoreFollowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public store page (buyer side, mobile plan §4). The owner's own store
 * with its bank and shipping settings is StoreResource (seller slice).
 *
 * @mixin Store
 */
class PublicStoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $liveSlug = app(LiveSessionService::class)->liveStoreIds()[$this->id] ?? null;

        return [
            'store_live' => $liveSlug !== null,
            'live_slug' => $liveSlug,
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo' => $this->getFirstMediaUrl('logo', 'thumb') ?: null,
            'banner' => $this->getFirstMediaUrl('banner', 'card') ?: null,
            'rating_avg' => (float) $this->rating_avg,
            'review_count' => (int) $this->rating_count,
            'product_count' => $this->products()->live()->count(),
            'followers_count' => app(StoreFollowService::class)->followersCount($this->resource),
            // Optional bearer token on this public route; guests are never following.
            'is_following' => app(StoreFollowService::class)->isFollowing($request->user('sanctum'), $this->resource),
            'joined_at' => ($this->approved_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
