<?php

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public marketing page pitching the marketplace to buyers AND sellers.
 * Guest-accessible at /welcome — distinct from the shopping home page ('/'),
 * which assumes intent to browse rather than to be persuaded.
 *
 * Rebuilt 2026-07-30 against the reference design concept (teardown in the
 * Hermes library: halalbizs-revamp-ref-skeleton.md). Uses the STOREFRONT layout
 * rather than the old minimal landing chrome, because the reference's screen
 * carries the full trust ticker, header, department row and footer — that
 * chrome is part of the design, not packaging around it.
 *
 * Every figure on this page is counted from the database. The reference's own
 * numbers ("2,140 verified SKUs", "186 audited brands") are its placeholder
 * copy and are deliberately not reproduced.
 */
#[Layout('layouts.storefront')]
class Landing extends Component
{
    public function render()
    {
        return view('livewire.storefront.landing', [
            'categories' => $this->topCategories(),
            'stats' => $this->stats(),
            'newlyVerified' => $this->newlyVerified(),
            'featuredStore' => $this->featuredStore(),
        ])->title(__('Malaysia’s Halal-First Marketplace'));
    }

    /** Departments for the category strip.
     *  ⚠ NOT cached: Cache::remember on an Eloquent collection serializes the
     *  models, and a stale entry comes back as __PHP_Incomplete_Class and 500s
     *  the page. Six rows by primary key is cheaper than that risk. Only plain
     *  scalars get cached on this page. */
    protected function topCategories(): Collection
    {
        return Category::active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->take(6)
            ->get();
    }

    /**
     * The three hero figures. "Recognised bodies" counts the DISTINCT certifying
     * authorities actually present in the catalogue, read off the certificate
     * number prefix — not a number anyone picked.
     *
     * @return array{products: int, stores: int, bodies: int}
     */
    protected function stats(): array
    {
        return Cache::remember('landing:stats', now()->addHour(), fn () => [
            'products' => Product::live()->count(),
            'stores' => Store::approved()->count(),
            'bodies' => Product::live()
                ->whereNotNull('halal_cert_number')
                ->pluck('halal_cert_number')
                ->map(fn (string $n): string => implode('-', array_slice(explode('-', $n), 0, 2)))
                ->unique()
                ->count(),
        ]);
    }

    /** Most recently published listings — the "newly verified" proof row. */
    protected function newlyVerified(): Collection
    {
        return Product::live()
            ->with(['variants', 'store'])
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(5)
            ->get();
    }

    /** One real seller for the brand-in-focus block: the widest catalogue that
     *  is not the house label (a marketplace should showcase a guest brand). */
    protected function featuredStore(): ?Store
    {
        // Not cached, for the same reason as topCategories().
        return Store::approved()
            ->where('slug', '!=', 'halalbizs-select')
            ->withCount(['products as listing_count' => fn ($q) => $q->live()])
            ->orderByDesc('listing_count')
            ->first();
    }
}
