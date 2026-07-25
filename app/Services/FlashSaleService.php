<?php

namespace App\Services;

use App\Enums\SubOrderStatus;
use App\Models\FlashSaleItem;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
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

    /**
     * Qty this buyer has already bought at this deal's promo price, across all
     * their orders — cancelled sub-orders (whose allocation was released) don't
     * count. Lets checkout enforce per_buyer_limit CUMULATIVELY, not per-order.
     */
    public function buyerPurchasedQty(FlashSaleItem $item, User $buyer): int
    {
        return (int) OrderItem::query()
            ->where('flash_sale_item_id', $item->id)
            ->whereHas('subOrder', fn ($q) => $q
                ->where('status', '!=', SubOrderStatus::Cancelled)
                ->whereHas('order', fn ($o) => $o->where('user_id', $buyer->id)))
            ->sum('qty');
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
