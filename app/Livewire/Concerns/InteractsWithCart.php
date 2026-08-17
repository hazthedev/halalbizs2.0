<?php

namespace App\Livewire\Concerns;

use App\Exceptions\CheckoutException;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use App\Services\CartService;

/**
 * Shared cart + wishlist actions for any Livewire component that renders
 * product cards or buy buttons. Optimistic UI happens client-side; these
 * methods reconcile (`cart-updated`) and toast the outcome.
 */
trait InteractsWithCart
{
    public function addToCart(int $variantId, int $qty = 1): bool
    {
        if (! app(CartService::class)->purchasingEnabled()) {
            $this->reconcileCart(__('This marketplace is currently in listing-only mode. Purchasing is unavailable.'), error: true);

            return false;
        }

        $variant = ProductVariant::with('product')->find($variantId);

        if ($variant === null || ! $variant->product->isLive() || $variant->product->store?->holiday_mode) {
            $this->reconcileCart(__('This product is not available right now.'), error: true);

            return false;
        }

        if ($variant->stock < 1) {
            $this->reconcileCart(__('Sorry — this item just sold out.'), error: true);

            return false;
        }

        try {
            app(CartService::class)->addItem(auth()->user(), $variant, $qty);
        } catch (CheckoutException $e) {
            $this->reconcileCart($e->getMessage(), error: true);

            return false;
        }

        $this->reconcileCart(__('Added to cart'), actionLabel: __('View cart'), actionEvent: 'view-cart');

        return true;
    }

    public function toggleWishlist(int $productId): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $existing = Wishlist::where('user_id', auth()->id())->where('product_id', $productId)->first();

        if ($existing !== null) {
            $existing->delete();
            $this->dispatch('toast', message: __('Removed from wishlist'));
        } else {
            Wishlist::firstOrCreate(['user_id' => auth()->id(), 'product_id' => $productId]);
            $this->dispatch('toast', message: __('Saved to wishlist'));
        }

        $this->dispatch('wishlist-updated');
    }

    /** Product ids in the current user's wishlist — for heart states. */
    public function wishlistedIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        // Cast to int: every caller tests membership with in_array(..., true), and
        // a plucked column can arrive as a numeric STRING, in which case the
        // strict compare is always false and no heart ever fills. Proven on the
        // preview — the row is written and the toast fires, but the button is
        // byte-identical afterwards and stays that way through a full reload.
        return Wishlist::where('user_id', auth()->id())
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function reconcileCart(string $message, bool $error = false, ?string $actionLabel = null, ?string $actionEvent = null): void
    {
        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
        $this->dispatch('toast', message: $message, type: $error ? 'error' : 'success', actionLabel: $actionLabel, actionEvent: $actionEvent);
    }
}
