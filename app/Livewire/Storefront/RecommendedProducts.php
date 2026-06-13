<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Services\RecommendationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * "Recommended for you" strip — personalised picks from RecommendationService,
 * reused on the home page, PDP and the buyer dashboard. Lazy-loaded so the
 * host page never waits on the recommender; renders nothing when empty.
 *
 * Auth buyer → server-side signals (purchases + views + wishlist). Guest →
 * localStorage viewed ids hydrated via loadViewed() (same pattern as
 * Home::loadRecentlyViewed) → category affinity, else popular.
 */
#[Lazy]
class RecommendedProducts extends Component
{
    use InteractsWithCart;

    public string $context = 'home';      // home | pdp | dashboard

    public ?int $excludeProductId = null; // the PDP's own product

    public int $limit = 12;

    /** Guest viewed-product ids, hydrated from localStorage. */
    public array $viewedIds = [];

    public bool $hydrated = false;

    public function mount(string $context = 'home', ?int $excludeProductId = null): void
    {
        $this->context = $context;
        $this->excludeProductId = $excludeProductId;
    }

    /** Guest hydration: Alpine passes window.recentlyViewed.all(). */
    public function loadViewed(array $ids): void
    {
        $this->viewedIds = array_slice(array_values(array_filter(array_map('intval', $ids))), 0, 20);
        $this->hydrated = true;
    }

    public function placeholder(): View
    {
        return view('livewire.storefront.partials.recommended-products-placeholder');
    }

    public function render(): View
    {
        $service = app(RecommendationService::class);

        if (auth()->check()) {
            $products = $service->forUser(auth()->user(), $this->limit, $this->excludeProductId);
        } elseif ($this->hydrated && $this->viewedIds !== []) {
            $products = $service->forViewedIds($this->viewedIds, $this->limit, $this->excludeProductId);
        } else {
            // First guest paint: wait for the Alpine hydrate before showing
            // popular, so the localStorage signal isn't missed.
            $products = $this->hydrated
                ? $service->popular($this->limit, $this->excludeProductId !== null ? [$this->excludeProductId] : [])
                : new Collection;
        }

        return view('livewire.storefront.recommended-products', [
            'products' => $products,
            'wishlistedIds' => $this->wishlistedIds(),
            'needsHydration' => ! auth()->check() && ! $this->hydrated,
        ]);
    }
}
