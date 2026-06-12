<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Settings\CodSettings;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class ProductDetail extends Component
{
    use InteractsWithCart;

    public Product $product;

    /** @var array<int, int> [product_option_id => product_option_value_id] */
    public array $selectedValues = [];

    public ?int $selectedVariantId = null;

    public int $qty = 1;

    /** Reviews tab filter: all | photos | 1–5 (docs/09 §C). */
    public string $reviewFilter = 'all';

    /** "Load more" page size for the reviews list. */
    public int $reviewsLimit = 5;

    public function mount(Product $product): void
    {
        abort_unless($product->isLive() || $this->canPreview($product), 404);

        $product->load(['options.values', 'variants.media', 'media', 'store', 'category', 'brand']);

        $this->product = $product;

        // Single-variant products resolve immediately — the picker is skipped.
        if ($product->variants->count() === 1) {
            $this->selectedVariantId = $product->variants->first()->id;
        }
    }

    public function selectValue(int $optionId, int $valueId): void
    {
        if (! $this->product->options->contains('id', $optionId)) {
            return;
        }

        if (($this->selectedValues[$optionId] ?? null) === $valueId) {
            unset($this->selectedValues[$optionId]);
        } else {
            $this->selectedValues[$optionId] = $valueId;
        }

        $this->resolveVariant();
    }

    public function incrementQty(): void
    {
        $max = $this->resolvedVariant()?->stock ?? 1;

        $this->qty = min($this->qty + 1, max(1, $max));
    }

    public function decrementQty(): void
    {
        $this->qty = max(1, $this->qty - 1);
    }

    public function buyNow(): void
    {
        $variant = $this->resolvedVariant();

        if ($variant === null || $variant->stock < 1) {
            return;
        }

        $this->addToCart($variant->id, $this->qty);

        $this->redirectRoute('cart', navigate: true);
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

    public function render()
    {
        return view('livewire.storefront.product-detail', [
            'variant' => $this->resolvedVariant(),
            'availability' => $this->availabilityMap(),
            'related' => $this->relatedProducts(),
            'wishlistedIds' => $this->wishlistedIds(),
            'codAvailable' => $this->product->cod_enabled && app(CodSettings::class)->enabled,
            'storeProductsCount' => $this->product->store?->products()->live()->count() ?? 0,
            'jsonLd' => $this->jsonLd(),
            'reviews' => $this->visibleReviews(),
            'hasMoreReviews' => $this->filteredReviewsQuery()->count() > $this->reviewsLimit,
            'reviewDistribution' => $this->reviewDistribution(),
        ])->title($this->product->getTranslation('name', app()->getLocale()));
    }

    private function canPreview(Product $product): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->id === $product->store?->user_id || $user->hasRole('admin');
    }

    private function resolvedVariant(): ?ProductVariant
    {
        if ($this->selectedVariantId === null) {
            return null;
        }

        return $this->product->variants->firstWhere('id', $this->selectedVariantId);
    }

    private function resolveVariant(): void
    {
        if (count($this->selectedValues) < $this->product->options->count()) {
            $this->selectedVariantId = $this->product->variants->count() === 1
                ? $this->product->variants->first()->id
                : null;

            return;
        }

        $variant = ProductVariant::resolveByValues($this->product->variants, array_values($this->selectedValues));

        $this->selectedVariantId = $variant?->id;

        if ($variant !== null && $variant->stock > 0) {
            $this->qty = min($this->qty, $variant->stock);
        }

        $this->qty = max(1, $this->qty);
    }

    /**
     * Per-chip availability: a value is available when at least one in-stock
     * variant carries it together with the values already selected in the
     * other option groups. Unavailable chips render disabled, never hidden.
     *
     * @return array<int, array<int, bool>> [option_id => [value_id => bool]]
     */
    private function availabilityMap(): array
    {
        $map = [];

        foreach ($this->product->options as $option) {
            foreach ($option->values as $value) {
                $selection = $this->selectedValues;
                $selection[$option->id] = $value->id;

                $map[$option->id][$value->id] = $this->product->variants->contains(
                    function (ProductVariant $variant) use ($selection) {
                        $ids = $variant->option_value_ids ?? [];

                        foreach ($selection as $valueId) {
                            if (! in_array($valueId, $ids, true)) {
                                return false;
                            }
                        }

                        return $variant->stock > 0;
                    }
                );
            }
        }

        return $map;
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

    private function relatedProducts()
    {
        return Product::query()
            ->live()
            ->whereKeyNot($this->product->id)
            ->where('category_id', $this->product->category_id)
            ->with(['variants', 'media', 'store'])
            ->orderByDesc('published_at')
            ->take(6)
            ->get();
    }

    /** @return array<string, mixed> */
    private function jsonLd(): array
    {
        $minSen = $this->product->minPriceSen();
        $maxSen = $this->product->maxPriceSen();
        $inStock = $this->product->variants->sum('stock') > 0;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->product->getTranslation('name', app()->getLocale()),
            'image' => $this->product->getMedia('images')->map(fn ($media) => $media->getUrl())->values()->all(),
            'description' => Str::of($this->product->getTranslation('description', app()->getLocale()))
                ->stripTags()->squish()->limit(500)->toString(),
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'MYR',
                'lowPrice' => sprintf('%d.%02d', intdiv($minSen, 100), $minSen % 100),
                'highPrice' => sprintf('%d.%02d', intdiv($maxSen, 100), $maxSen % 100),
                'offerCount' => $this->product->variants->count(),
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($this->product->rating_count > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $this->product->rating_avg,
                'reviewCount' => $this->product->rating_count,
            ];
        }

        return $schema;
    }
}
