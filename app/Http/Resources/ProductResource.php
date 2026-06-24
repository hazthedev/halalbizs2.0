<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'description' => $this->getTranslation('description', app()->getLocale()),
            'min_price_sen' => $this->variants->isNotEmpty() ? $this->minPriceSen() : 0,
            'sold_count' => $this->sold_count,
            'rating_avg' => (float) $this->rating_avg,
            'image' => $this->getFirstMediaUrl('images', 'card') ?: null,
            'store' => $this->whenLoaded('store', fn () => [
                'name' => $this->store?->name,
                'slug' => $this->store?->slug,
            ]),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'options_label' => $variant->options_label,
                'price_sen' => $variant->effectivePriceSen(),
                'stock' => $variant->stock,
            ])->values()),
        ];
    }
}
