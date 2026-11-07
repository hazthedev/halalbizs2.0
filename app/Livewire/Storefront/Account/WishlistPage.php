<?php

namespace App\Livewire\Storefront\Account;

use App\Livewire\Concerns\InteractsWithCart;
use App\Services\WishlistService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.storefront')]
class WishlistPage extends Component
{
    use InteractsWithCart;

    #[On('wishlist-updated')]
    public function refreshWishlist(): void
    {
        // Handling the event is enough — Livewire re-renders the grid.
    }

    public function render()
    {
        return view('livewire.storefront.account.wishlist-page', [
            'products' => app(WishlistService::class)->products(auth()->user()),
        ])->title(__('Wishlist'));
    }
}
