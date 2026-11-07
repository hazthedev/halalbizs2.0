<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductPublishPolicy;
use App\Support\ContentLocales;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The list row plus everything the edit screen needs.
 *
 * @mixin Product
 */
class SellerProductDetailResource extends SellerProductResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $default = $this->variants->firstWhere('is_default', true) ?? $this->variants->first();

        return parent::toArray($request) + [
            'description' => $this->getTranslation('description', app()->getLocale()),
            'description_translations' => ContentLocales::read($this->resource, 'description'),
            'condition' => $this->condition?->value,
            'variants' => $this->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'options_label' => $variant->options_label,
                'sku' => $variant->sku,
                'price_sen' => $variant->price_sen,
                'sale_price_sen' => $variant->sale_price_sen,
                'stock' => $variant->stock,
                'is_default' => $variant->is_default,
                'image' => $variant->getFirstMediaUrl('image', 'thumb') ?: null,
            ])->values(),
            'images' => $this->getMedia('images')->map(fn (Media $media) => [
                'id' => $media->id,
                'url' => $media->getUrl('card'),
                'thumb' => $media->getUrl('thumb'),
            ])->values(),
            'weight_grams' => $this->weight_grams,
            'length_mm' => $this->length_mm,
            'width_mm' => $this->width_mm,
            'height_mm' => $this->height_mm,
            'sale_starts_at' => $default?->sale_starts_at?->toIso8601String(),
            'sale_ends_at' => $default?->sale_ends_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'blocked_reason' => app(ProductPublishPolicy::class)->blockedReason($this->resource),
        ];
    }
}
