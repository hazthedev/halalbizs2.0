<?php

namespace App\Livewire\Storefront;

use App\Enums\BoostStatus;
use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Banner;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\ThemeAsset;
use App\Settings\BoostSettings;
use App\Settings\ThemeSettings;
use Illuminate\Database\Eloquent\Builder;
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

        $theme = app(ThemeSettings::class);

        return view('livewire.storefront.home', [
            'sections' => $sections,
            'wishlistedIds' => $this->wishlistedIds(),
            'heroUrl' => $this->heroUrl($theme),
            'occasion' => $theme->occasion,
        ]);
    }

    /** Occasion hero image URL — only when enabled, in window, and uploaded. */
    protected function heroUrl(ThemeSettings $theme): ?string
    {
        if (! $theme->heroActive()) {
            return null;
        }

        $url = ThemeAsset::where('key', 'hero')->first()?->getFirstMediaUrl('image', 'card');

        return $url !== null && $url !== '' ? $url : null;
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
        // 'top' leads with actively boosted products (flagged Sponsored),
        // then fills the remaining slots organically by sold_count.
        $boosted = $source === 'top' ? $this->boostedProducts($limit) : new Collection;

        $query = Product::live()->with(['media', 'variants', 'store']);

        if ($boosted->isNotEmpty()) {
            $query->whereNotIn('id', $boosted->modelKeys());
        }

        match ($source) {
            'top' => $query->orderByDesc('sold_count'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $boosted->concat($query->take($limit - $boosted->count())->get());
    }

    /**
     * Actively boosted live products, newest boost first, flagged for the
     * neutral Sponsored badge on the card.
     *
     * @return Collection<int, Product>
     */
    protected function boostedProducts(int $limit): Collection
    {
        if (! app(BoostSettings::class)->enabled) {
            return new Collection;
        }

        $latestBoostStart = ProductBoost::select('starts_at')
            ->whereColumn('product_id', 'products.id')
            ->where('status', BoostStatus::Active)
            ->orderByDesc('starts_at')
            ->limit(1);

        return Product::live()
            ->with(['media', 'variants', 'store'])
            ->whereHas('boosts', fn (Builder $boosts) => $boosts->active())
            ->orderByDesc($latestBoostStart)
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->each(fn (Product $product) => $product->sponsored = true);
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
