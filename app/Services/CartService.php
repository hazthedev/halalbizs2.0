<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Guest cart lives in the session as [variant_id => qty];
 * buyer cart lives in the DB. Session cart merges into DB on login.
 */
class CartService
{
    private const SESSION_KEY = 'guest_cart';

    public function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function addItem(?User $user, ProductVariant $variant, int $qty = 1): void
    {
        $qty = max(1, min($qty, $variant->stock));

        if ($user === null) {
            $items = Session::get(self::SESSION_KEY, []);
            $items[$variant->id] = min(($items[$variant->id] ?? 0) + $qty, $variant->stock);
            Session::put(self::SESSION_KEY, $items);

            return;
        }

        $cart = $this->cartFor($user);
        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item !== null) {
            $item->update(['qty' => min($item->qty + $qty, $variant->stock)]);

            return;
        }

        $cart->items()->create(['product_variant_id' => $variant->id, 'qty' => $qty]);
    }

    public function updateQty(?User $user, ProductVariant $variant, int $qty): void
    {
        if ($qty < 1) {
            $this->removeItem($user, $variant);

            return;
        }

        $qty = min($qty, $variant->stock);

        if ($user === null) {
            $items = Session::get(self::SESSION_KEY, []);
            $items[$variant->id] = $qty;
            Session::put(self::SESSION_KEY, $items);

            return;
        }

        $this->cartFor($user)->items()
            ->where('product_variant_id', $variant->id)
            ->update(['qty' => $qty]);
    }

    public function removeItem(?User $user, ProductVariant $variant): void
    {
        if ($user === null) {
            $items = Session::get(self::SESSION_KEY, []);
            unset($items[$variant->id]);
            Session::put(self::SESSION_KEY, $items);

            return;
        }

        $this->cartFor($user)->items()->where('product_variant_id', $variant->id)->delete();
    }

    /** @return array<int, int> [variant_id => qty] */
    public function sessionItems(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function mergeSessionCart(User $user): void
    {
        $sessionItems = $this->sessionItems();

        if ($sessionItems === []) {
            return;
        }

        $variants = ProductVariant::whereIn('id', array_keys($sessionItems))->get();

        foreach ($variants as $variant) {
            $this->addItem($user, $variant, $sessionItems[$variant->id]);
        }

        Session::forget(self::SESSION_KEY);
    }

    public function itemCount(?User $user): int
    {
        if ($user === null) {
            return (int) array_sum($this->sessionItems());
        }

        return (int) $this->cartFor($user)->items()->sum('qty');
    }

    /**
     * Cart lines grouped by store, with live variant+product data.
     * Works for both guest (session) and buyer (DB) carts.
     *
     * @return Collection<int, Collection> keyed by store_id
     */
    public function groupedByStore(?User $user): Collection
    {
        if ($user === null) {
            $sessionItems = $this->sessionItems();
            $variants = ProductVariant::with(['product.store', 'product.media', 'media'])
                ->whereIn('id', array_keys($sessionItems))
                ->get();

            $lines = $variants->map(fn (ProductVariant $variant) => (object) [
                'variant' => $variant,
                'qty' => $sessionItems[$variant->id],
                'selected' => true,
            ]);
        } else {
            $items = $this->cartFor($user)->items()
                ->with(['variant.product.store', 'variant.product.media', 'variant.media'])
                ->get();

            $lines = $items->map(fn ($item) => (object) [
                'variant' => $item->variant,
                'qty' => $item->qty,
                'selected' => $item->selected,
            ]);
        }

        return $lines
            ->filter(fn ($line) => $line->variant?->product !== null)
            ->groupBy(fn ($line) => $line->variant->product->store_id);
    }
}
