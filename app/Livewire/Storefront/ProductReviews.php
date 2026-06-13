<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * PDP reviews tab content, lazy-loaded: the initial product page skips the
 * review queries and the tab hydrates when it scrolls/toggles into view.
 * Filter + "load more" behaviour moved here from ProductDetail (docs/09 §C).
 */
#[Lazy]
class ProductReviews extends Component
{
    public Product $product;

    /** Reviews tab filter: all | photos | 1–5 (docs/09 §C). */
    public string $reviewFilter = 'all';

    /** "Load more" page size for the reviews list. */
    public int $reviewsLimit = 5;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function setReviewFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'photos', '1', '2', '3', '4', '5'], true)) {
            return;
        }

        $this->reviewFilter = $filter;
        $this->reviewsLimit = 5;
    }

    public function loadMoreReviews(): void
    {
        $this->reviewsLimit += 5;
    }

    public function placeholder(): View
    {
        return view('livewire.storefront.partials.product-reviews-placeholder');
    }

    public function render()
    {
        return view('livewire.storefront.product-reviews', [
            'reviews' => $this->visibleReviews(),
            'hasMoreReviews' => $this->filteredReviewsQuery()->count() > $this->reviewsLimit,
            'reviewDistribution' => $this->reviewDistribution(),
        ]);
    }

    /** Visible reviews under the active filter, newest first. */
    private function visibleReviews()
    {
        return $this->filteredReviewsQuery()
            ->with(['user', 'media', 'orderItem'])
            ->latest()
            ->latest('id')
            ->take($this->reviewsLimit)
            ->get();
    }

    private function filteredReviewsQuery()
    {
        return $this->product->reviews()
            ->visible()
            ->when($this->reviewFilter === 'photos', fn ($query) => $query->whereHas(
                'media', fn ($mediaQuery) => $mediaQuery->where('collection_name', 'photos')
            ))
            ->when(in_array($this->reviewFilter, ['1', '2', '3', '4', '5'], true), fn ($query) => $query->where(
                'rating', (int) $this->reviewFilter
            ));
    }

    /** @return array<int, int> star (5→1) → visible review count */
    private function reviewDistribution(): array
    {
        $counts = Review::query()
            ->toBase()
            ->where('product_id', $this->product->id)
            ->where('is_hidden', false)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return collect([5, 4, 3, 2, 1])
            ->mapWithKeys(fn (int $star) => [$star => (int) ($counts[$star] ?? 0)])
            ->all();
    }
}
