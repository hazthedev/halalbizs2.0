<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Snapshots are sacred: product_name, variant_label, unit_price_sen are
 * fixed at purchase time — never re-read live product data (hard rule 5).
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_order_id', 'product_id', 'product_variant_id', 'group_buy_id',
        'product_name', 'variant_label', 'unit_price_sen', 'qty', 'line_total_sen',
        'tax_sen', 'tax_rate_bp',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_sen' => 'integer',
            'qty' => 'integer',
            'line_total_sen' => 'integer',
            'tax_sen' => 'integer',
            'tax_rate_bp' => 'integer',
        ];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
