<?php

namespace App\Livewire\Storefront\Account;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Models\Wishlist;
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
        $productIds = Wishlist::where('user_id', auth()->id())
            ->latest()
            ->pluck('product_id');

        $products = Product::with(['variants', 'store', 'media'])
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();

        return view('livewire.storefront.account.wishlist-page', [
            'products' => $products,
        ])->title(__('Wishlist'));
    }
}
