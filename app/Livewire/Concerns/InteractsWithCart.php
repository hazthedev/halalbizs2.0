<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BuyerActionException;
use App\Exceptions\CheckoutException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\WishlistService;

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

        $variant = ProductVariant::with('product.store')->find($variantId);

        if ($variant === null) {
            $this->reconcileCart(__('This product is not available right now.'), error: true);

            return false;
        }

        // Live / holiday / sold-out checks live in CartService::addItem (shared with the API).
        try {
            app(CartService::class)->addItem(auth()->user(), $variant, $qty);
        } catch (CheckoutException|BuyerActionException $e) {
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

        $product = Product::find($productId);

        if ($product === null) {
            $this->dispatch('toast', message: __('This product is not available right now.'), type: 'error');

            return;
        }

        $saved = app(WishlistService::class)->toggle(auth()->user(), $product);

        $this->dispatch('toast', message: $saved ? __('Saved to wishlist') : __('Removed from wishlist'));
        $this->dispatch('wishlist-updated');
    }

    /** Product ids in the current user's wishlist — for heart states. */
    public function wishlistedIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return app(WishlistService::class)->ids(auth()->user());
    }

    private function reconcileCart(string $message, bool $error = false, ?string $actionLabel = null, ?string $actionEvent = null): void
    {
        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
        $this->dispatch('toast', message: $message, type: $error ? 'error' : 'success', actionLabel: $actionLabel, actionEvent: $actionEvent);
    }
}
