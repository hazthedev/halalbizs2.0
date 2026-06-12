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
    public string $query = '';

    public function render()
    {
        $products = collect();
        $stores = collect();
        $categories = collect();

        $term = trim($this->query);

        if (mb_strlen($term) >= 2) {
            $products = Product::search($term)->take(6)->get()->load(['media', 'variants', 'store']);

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

        return view('livewire.storefront.layout.search-overlay', [
            'products' => $products,
            'stores' => $stores,
            'categories' => $categories,
            'trending' => $trending,
        ]);
    }
}
