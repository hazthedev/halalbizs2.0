<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Flash-sale pricing + allocation (docs/ROADMAP.md M1.2). Resolves the live
 * promo price for a variant and exposes the live deal lines for the storefront.
 * Allocation is consumed by CheckoutService under lockForUpdate (Hard Rule 3).
 */
class FlashSaleService
{
    /**
     * Live flash-sale lines for a set of variants, keyed by variant id.
     *
     * @param  array<int>  $variantIds
     * @return Collection<int, FlashSaleItem>
     */
    public function liveItemsFor(array $variantIds, bool $lock = false): Collection
    {
        $variantIds = array_values(array_filter($variantIds));

        if ($variantIds === []) {
            return new Collection;
        }

        $query = FlashSaleItem::query()
            ->whereIn('product_variant_id', $variantIds)
            ->whereHas('flashSale', fn ($sale) => $sale->live());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('product_variant_id');
    }

    public function liveItemFor(int $variantId): ?FlashSaleItem
    {
        return $this->liveItemsFor([$variantId])->get($variantId);
    }

    /** Promo price when a deal is live with allocation left, else the normal price. */
    public function priceFor(ProductVariant $variant): int
    {
        $item = $this->liveItemFor($variant->id);

        return $item !== null && $item->remaining() > 0
            ? $item->promo_price_sen
            : $variant->effectivePriceSen();
    }
}
