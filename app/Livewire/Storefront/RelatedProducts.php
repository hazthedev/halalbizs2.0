<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
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
