<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An immutable record of a single stock change (never updated). */
class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_variant_id', 'type', 'qty_delta', 'balance_after', 'reference', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty_delta' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
