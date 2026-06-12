<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Banner;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Home extends Component
{
    use InteractsWithCart;

    /** Product ids hydrated from localStorage by Alpine (newest first). */
    public array $recentlyViewedIds = [];

    /** Called from the view's x-init with window.recentlyViewed.all(). */
    public function loadRecentlyViewed(array $ids): void
    {
        $this->recentlyViewedIds = collect($ids)
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    public function render()
    {
        $sections = HomeSection::active()->get()
            ->map(fn (HomeSection $section) => [
                'section' => $section,
                'data' => $this->sectionData($section),
            ])
            // Empty sections disappear — except recently_viewed, whose wrapper
            // must render so Alpine can hydrate ids from localStorage.
            ->reject(fn (array $row) => $row['data']->isEmpty() && $row['section']->type !== 'recently_viewed')
            ->values();

        return view('livewire.storefront.home', [
            'sections' => $sections,
            'wishlistedIds' => $this->wishlistedIds(),
        ]);
    }

    protected function sectionData(HomeSection $section): Collection
    {
        return match ($section->type) {
            'banner' => Banner::active()->with('media')->get(),
            'category_grid' => Category::active()
                ->whereNull('parent_id')
                ->with('media')
                ->orderBy('position')
                ->take((int) ($section->payload['limit'] ?? 8))
                ->get(),
            'product_carousel' => $this->products(
                $section->payload['source'] ?? 'latest',
                (int) ($section->payload['limit'] ?? 12),
            ),
            'product_grid' => $this->products(
                $section->payload['source'] ?? 'top',
                (int) ($section->payload['limit'] ?? 12),
            ),
            'recently_viewed' => $this->recentlyViewedProducts(),
            default => new Collection,
        };
    }

    /** @return Collection<int, Product> */
    protected function products(string $source, int $limit): Collection
    {
        $query = Product::live()->with(['media', 'variants', 'store']);

        match ($source) {
            'top' => $query->orderByDesc('sold_count'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->take($limit)->get();
    }

    /** @return Collection<int, Product> */
    protected function recentlyViewedProducts(): Collection
    {
        if ($this->recentlyViewedIds === []) {
            return new Collection;
        }

        return Product::live()
            ->with(['media', 'variants', 'store'])
            ->whereIn('id', $this->recentlyViewedIds)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $this->recentlyViewedIds))
            ->values();
    }
}
