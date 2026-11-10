<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** detail: true adds the gallery (`images[]`) and the option groups for the picker. */
    public function __construct(Product $resource, private bool $detail = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
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
                'regular_price_sen' => $variant->isOnSale() ? $variant->price_sen : null,
                'option_value_ids' => array_map('intval', $variant->option_value_ids ?? []),
                'stock' => $variant->stock,
            ])->values()),
        ];

        if (! $this->detail) {
            return $data;
        }

        // Same groups and order as the web picker (ProductDetail: options.values by position).
        return $data + [
            'images' => $this->getMedia('images')->map(fn ($media) => [
                'large' => $media->getUrl(),
                'card' => $media->getUrl('card'),
            ])->values()->all(),
            'options' => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'values' => $option->values->map(fn ($value) => ['id' => $value->id, 'value' => $value->value])->values()->all(),
            ])->values()->all(),
        ];
    }
}
