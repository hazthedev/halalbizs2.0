<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;

/**
 * The single path for stock changes (docs/ROADMAP.md M0.5). Applies the delta
 * and writes an immutable movement carrying the resulting balance, so every
 * checkout decrement, restock and adjustment is traceable. Callers run it
 * inside their own lock/transaction (e.g. CheckoutService).
 */
class StockService
{
    public function apply(ProductVariant $variant, int $qtyDelta, StockMovementType $type, ?string $reference = null): StockMovement
    {
        if ($qtyDelta >= 0) {
            $variant->increment('stock', $qtyDelta);
        } else {
            $variant->decrement('stock', -$qtyDelta);
        }

        return StockMovement::create([
            'product_variant_id' => $variant->id,
            'type' => $type,
            'qty_delta' => $qtyDelta,
            'balance_after' => (int) $variant->stock, // increment/decrement updates the in-memory attribute
            'reference' => $reference,
            'created_at' => now(),
        ]);
    }
}
