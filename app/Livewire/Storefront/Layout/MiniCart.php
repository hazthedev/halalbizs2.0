<?php

namespace App\Livewire\Storefront\Layout;

use App\Models\ProductVariant;
use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class MiniCart extends Component
{
    public function updateQty(int $variantId, int $qty): void
    {
        $variant = ProductVariant::find($variantId);

        if ($variant !== null) {
            app(CartService::class)->updateQty(auth()->user(), $variant, $qty);
        }

        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
    }

    public function removeLine(int $variantId): void
    {
        $variant = ProductVariant::find($variantId);

        if ($variant !== null) {
            app(CartService::class)->removeItem(auth()->user(), $variant);
        }

        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
        $this->dispatch('toast', message: __('Removed from cart'), actionLabel: __('Undo'), actionEvent: 'undo-remove', actionPayload: ['variantId' => $variantId]);
    }

    #[On('undo-remove')]
    public function undoRemove(int $variantId): void
    {
        $variant = ProductVariant::find($variantId);

        // CartPage listens for the same event and restores the original qty —
        // only re-add here when no other listener already has.
        if ($variant !== null && ! app(CartService::class)->hasItem(auth()->user(), $variantId)) {
            app(CartService::class)->addItem(auth()->user(), $variant, 1);
        }

        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
    }

    #[On('cart-updated')]
    public function render()
    {
        $groups = app(CartService::class)->groupedByStore(auth()->user());

        $subtotalSen = $groups->flatten(1)->sum(
            fn ($line) => $line->variant->effectivePriceSen() * $line->qty
        );

        return view('livewire.storefront.layout.mini-cart', [
            'groups' => $groups,
            'subtotalSen' => $subtotalSen,
        ]);
    }
}
