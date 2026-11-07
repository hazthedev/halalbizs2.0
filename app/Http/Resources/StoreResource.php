<?php

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Store */
class StoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo' => $this->getFirstMediaUrl('logo', 'thumb') ?: null,
            'banner' => $this->getFirstMediaUrl('banner', 'card') ?: null,
            'rating_avg' => (float) $this->rating_avg,
            'review_count' => (int) $this->rating_count,
            'product_count' => $this->products()->live()->count(),
            'joined_at' => ($this->approved_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
