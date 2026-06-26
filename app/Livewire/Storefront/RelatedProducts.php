<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Services\CartService;
use App\Services\RecommendationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * PDP "Related products" strip, lazy-loaded so the initial product page
 * skips the related query. The placeholder mirrors the real strip
 * dimensions (heading + 6 card skeletons) — no layout shift (design §9).
 */
#[Lazy]
class RelatedProducts extends Component
{
    use InteractsWithCart;

    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    /** "Add all to cart" for the frequently-bought-together strip — one in-stock variant each, single toast. */
    public function addAllBoughtTogether(): void
    {
        $cart = app(CartService::class);
        $added = 0;

        foreach (app(RecommendationService::class)->frequentlyBoughtTogether($this->product->id) as $product) {
            $variant = $product->variants->firstWhere(fn ($v) => $v->stock > 0);

            if ($variant !== null) {
                $cart->addItem(auth()->user(), $variant, 1);
                $added++;
            }
        }

        if ($added > 0) {
            $this->reconcileCart(
                trans_choice('{1}:count item added to cart|[2,*]:count items added to cart', $added, ['count' => $added]),
                actionLabel: __('View cart'),
                actionEvent: 'view-cart',
            );
        }
    }

    public function placeholder(): View
    {
        return view('livewire.storefront.partials.related-products-placeholder');
    }

    public function render()
    {
        return view('livewire.storefront.related-products', [
            'related' => Product::query()
                ->live()
                ->whereKeyNot($this->product->id)
                ->where('category_id', $this->product->category_id)
                ->with(['variants', 'media', 'store'])
                ->orderByDesc('published_at')
                ->take(6)
                ->get(),
            // Frequently bought together — co-purchase, falls back to empty (M1.8).
            'boughtTogether' => app(RecommendationService::class)->frequentlyBoughtTogether($this->product->id),
            'wishlistedIds' => $this->wishlistedIds(),
        ]);
    }
}
