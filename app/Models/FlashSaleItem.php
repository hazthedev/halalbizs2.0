<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_sale_id', 'product_variant_id', 'promo_price_sen', 'allocated_qty', 'per_buyer_limit', 'sold_qty',
    ];

    protected function casts(): array
    {
        return [
            'promo_price_sen' => 'integer',
            'allocated_qty' => 'integer',
            'per_buyer_limit' => 'integer',
            'sold_qty' => 'integer',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function remaining(): int
    {
        return max(0, $this->allocated_qty - $this->sold_qty);
    }

    /** Integer percentage of the allocation claimed (for the live progress bar). */
    public function percentClaimed(): int
    {
        return $this->allocated_qty > 0 ? min(100, intdiv($this->sold_qty * 100, $this->allocated_qty)) : 100;
    }
}
