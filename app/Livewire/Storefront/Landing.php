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
 */
#[Layout('layouts.storefront')]
class Landing extends Component
{
    public function render()
    {
        return view('livewire.storefront.landing', [
            'categories' => $this->topCategories(),
            'stats' => $this->stats(),
        ])->title(__('Malaysia’s Halal-First Marketplace'));
    }

    /** Top-level categories for the showcase strip — capped, position order. */
    protected function topCategories(): Collection
    {
        return Category::active()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->take(8)
            ->get();
    }

    /** Cheap, real counts for the stats band — cached, no invented numbers. */
    protected function stats(): array
    {
        return Cache::remember('landing:stats', now()->addHour(), fn () => [
            'stores' => Store::approved()->count(),
            'products' => Product::live()->count(),
            'categories' => Category::active()->count(),
        ]);
    }
}
