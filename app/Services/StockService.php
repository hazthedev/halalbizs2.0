<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Events\ProductRestocked;
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
        $before = (int) $variant->stock;

        // Oversell floor (defense-in-depth): the caller is expected to hold the
        // variant row lock and check availability first (CheckoutService does).
        // Any other caller that decrements past the balance fails loudly here
        // rather than silently driving stock negative — stock is never < 0.
        if ($qtyDelta < 0 && $before + $qtyDelta < 0) {
            throw new \RuntimeException(
                "Stock underflow for variant {$variant->id}: have {$before}, cannot apply {$qtyDelta}."
            );
        }

        if ($qtyDelta >= 0) {
            $variant->increment('stock', $qtyDelta);
        } else {
            $variant->decrement('stock', -$qtyDelta);
        }

        // Crossed out-of-stock → in-stock: alert anyone waiting on this variant.
        if ($before < 1 && (int) $variant->stock >= 1) {
            ProductRestocked::dispatch($variant);
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
