<?php

namespace App\Livewire\Storefront\Layout;

use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SearchOverlay extends Component
{
    /** Below this many real trending terms, the empty state reads as broken. */
    private const TRENDING_LOOKS_BARE = 3;

    public string $query = '';

    public function render()
    {
        $products = collect();
        $stores = collect();
        $categories = collect();

        $term = trim($this->query);

        if (mb_strlen($term) >= 2) {
            $products = (config('scout.driver') === 'meilisearch'
                ? Product::search($term)->take(6)->get()
                : Product::query()->live()->keywordSearch($term)->orderByDesc('sold_count')->take(6)->get()
            )->load(['media', 'variants', 'store']);

            $stores = Store::approved()
                ->where('name', 'like', "%{$term}%")
                ->take(3)
                ->get();

            $categories = Category::active()
                ->where('name', 'like', "%{$term}%")
                ->take(3)
                ->get();
        }

        // Cache a plain array — cache.serializable_classes is false (gadget-chain
        // hardening), so objects come back as __PHP_Incomplete_Class.
        $trending = collect(Cache::remember('search:trending', now()->addHour(), function () {
            return SearchLog::where('created_at', '>=', now()->subDays(7))
                ->where('results_count', '>', 0)
                ->selectRaw('term, count(*) as searches')
                ->groupBy('term')
                ->orderByDesc('searches')
                ->limit(6)
                ->pluck('term')
                ->all();
        }));

        // Trending is real search volume, so on a quiet catalogue it is honestly
        // one or two terms — which left the overlay opening on a single lonely
        // chip. Top the empty state up with categories rather than padding
        // "Trending" with things nobody searched for: a category chip is a real
        // destination, and it does not claim to be popular.
        $browseCategories = $trending->count() >= self::TRENDING_LOOKS_BARE
            ? collect()
            // ⚠ Resolve through the MODEL, not pluck(): `name` is translatable,
            // so pluck() returns the raw {"en":…,"ms":…} JSON. And cache plain
            // strings, not models — cache.serializable_classes is false, so
            // cached Eloquent comes back as __PHP_Incomplete_Class.
            : collect(Cache::remember('search:overlay-categories:'.app()->getLocale(), now()->addHour(), fn () => Category::active()
                ->whereNull('parent_id')
                ->orderBy('position')
                ->limit(8)
                ->get()
                ->mapWithKeys(fn (Category $c) => [$c->slug => (string) $c->name])
                ->all()));

        return view('livewire.storefront.layout.search-overlay', [
            'products' => $products,
            'stores' => $stores,
            'categories' => $categories,
            'trending' => $trending,
            'browseCategories' => $browseCategories,
        ]);
    }
}
