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
        $categories = Category::active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->take(6)
            ->get();

        // M-21: the tile blade used to run FOUR queries per category inside its
        // @foreach — two child-id lookups, an ORDER BY RAND() sample and a count
        // — so six departments cost 24 queries, six of them random-sorts, on an
        // uncached page. Resolved here in two: one grouped count, and one pass
        // for the sample images.
        //
        // ⚠ descendantIds(), not direct children. The old blade counted only
        // immediate children, so on a three-level tree — which is what the real
        // catalogue has — every tile read "No listings yet" while the department
        // page it links to showed 94 products. Category::descendantIds() is what
        // Listing uses for exactly this question, and a tile that disagrees with
        // the page behind it is worse than no tile.
        // The whole tree in ONE query, then walked in PHP. Calling
        // descendantIds() directly here would be correct but lazy-loads
        // `children` at every level, which put the queries back up — the same
        // shape of bug this method exists to remove. The test asserts this
        // agrees with descendantIds() so the two cannot drift.
        $childrenByParent = Category::query()
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $descendants = [];

        $walk = function (int $categoryId, int $departmentId) use (&$walk, $childrenByParent, &$descendants): void {
            $descendants[$categoryId] = $departmentId;

            foreach ($childrenByParent->get($categoryId, collect()) as $child) {
                $walk((int) $child->id, $departmentId);
            }
        };

        foreach ($categories as $category) {
            $walk((int) $category->id, (int) $category->id);
        }

        $countsByCategory = Product::live()
            ->whereIn('category_id', array_keys($descendants))
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        // One image per department. ORDER BY RAND() over the whole catalogue was
        // the expensive half and buys nothing on a tile — the newest listing with
        // artwork is a better shop window anyway, and it is index-ordered.
        $samples = Product::live()
            ->whereIn('category_id', array_keys($descendants))
            ->has('media')
            ->with('media')
            ->orderByDesc('id')
            ->get(['id', 'category_id'])
            ->groupBy(fn (Product $p) => $descendants[$p->category_id] ?? null);

        $totals = [];

        foreach ($countsByCategory as $categoryId => $count) {
            $department = $descendants[$categoryId] ?? null;

            if ($department !== null) {
                $totals[$department] = ($totals[$department] ?? 0) + (int) $count;
            }
        }

        return $categories->each(function (Category $category) use ($totals, $samples): void {
            $category->setAttribute('tile_count', $totals[$category->id] ?? 0);
            $category->setAttribute('tile_sample', $samples->get($category->id)?->first());
        });
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
            ->with(['halalCertificate', 'variants', 'store'])
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
