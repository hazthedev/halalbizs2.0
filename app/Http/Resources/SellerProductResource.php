<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\ContentLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Seller products list row (mobile plan §4). Load media, variants and
 * category first. `price_sen`/`sale_price_sen` are the default variant's;
 * `min_price_sen` is the catalogue figure across every variant.
 *
 * @mixin Product
 */
class SellerProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $default = $this->variants->firstWhere('is_default', true) ?? $this->variants->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'name_translations' => ContentLocales::read($this->resource, 'name'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->getTranslation('name', app()->getLocale()),
            ],
            'thumb' => $this->getFirstMediaUrl('images', 'thumb') ?: null,
            'price_sen' => $default?->price_sen,
            'sale_price_sen' => $default?->sale_price_sen,
            'min_price_sen' => $this->variants->isNotEmpty() ? $this->minPriceSen() : 0,
            'stock_total' => (int) $this->variants->sum('stock'),
            'variants_count' => $this->variants->count(),
            'cod_enabled' => $this->cod_enabled,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
