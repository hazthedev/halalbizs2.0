<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'product_id', 'sku', 'options_label', 'option_value_ids',
        'price_sen', 'sale_price_sen', 'sale_starts_at', 'sale_ends_at',
        'stock', 'is_default', 'position',
    ];

    protected function casts(): array
    {
        return [
            'option_value_ids' => 'array',
            'price_sen' => 'integer',
            'sale_price_sen' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'stock' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isOnSale(): bool
    {
        if ($this->sale_price_sen === null) {
            return false;
        }

        $now = now();

        if ($this->sale_starts_at !== null && $now->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at !== null && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    public function effectivePriceSen(): int
    {
        return $this->isOnSale() ? $this->sale_price_sen : $this->price_sen;
    }

    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || $this->price_sen === 0 || $this->sale_price_sen >= $this->price_sen) {
            return null;
        }

        return (int) round((($this->price_sen - $this->sale_price_sen) * 100) / $this->price_sen);
    }

    /**
     * Match a variant by its option value ids, resolved in PHP (docs/04 §7).
     *
     * @param  Collection<int, self>  $variants
     * @param  array<int>  $valueIds
     */
    public static function resolveByValues(Collection $variants, array $valueIds): ?self
    {
        sort($valueIds);

        return $variants->first(function (self $variant) use ($valueIds) {
            $ids = $variant->option_value_ids ?? [];
            sort($ids);

            return $ids === $valueIds;
        });
    }
}
