<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductView;
use App\Models\StockSubscription;
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

    public function mount(Product $product): void
    {
        abort_unless($product->isLive() || $this->canPreview($product), 404);

        $product->load(['options.values', 'variants.media', 'media', 'store', 'category', 'brand', 'metafields']);

        $this->product = $product;

        // Single-variant products resolve immediately — the picker is skipped.
        if ($product->variants->count() === 1) {
            $this->selectedVariantId = $product->variants->first()->id;
        }

        $this->recordView($product);
    }

    /**
     * Server-side view signal for recommendations — one upsert per buyer ×
     * product, recency-bumped. Guests rely on the localStorage strip instead.
     */
    private function recordView(Product $product): void
    {
        if (auth()->check() && $product->isLive()) {
            ProductView::updateOrCreate(
                ['user_id' => auth()->id(), 'product_id' => $product->id],
                ['viewed_at' => now()],
            );
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

    /** Register a one-shot back-in-stock alert for an out-of-stock variant. */
    public function notifyWhenAvailable(int $variantId): void
    {
        if (auth()->guest()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $variant = $this->product->variants->firstWhere('id', $variantId);

        abort_if($variant === null, 404);

        StockSubscription::firstOrCreate([
            'user_id' => auth()->id(),
            'product_variant_id' => $variant->id,
        ]);

        $this->dispatch('toast', message: __("Done — we'll email you the moment it's back in stock."));
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

    public function render()
    {
        // Reviews tab + related strip are #[Lazy] children (ProductReviews,
        // RelatedProducts) — their queries no longer run on first paint.
        return view('livewire.storefront.product-detail', [
            'variant' => $this->resolvedVariant(),
            'availability' => $this->availabilityMap(),
            'wishlistedIds' => $this->wishlistedIds(),
            'codAvailable' => $this->product->cod_enabled && app(CodSettings::class)->enabled,
            'storeProductsCount' => $this->product->store?->products()->live()->count() ?? 0,
            'jsonLd' => $this->jsonLd(),
            'subscribedVariantIds' => $this->subscribedVariantIds(),
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

    /**
     * Variant ids this buyer already has a live back-in-stock alert on, so the
     * button can render its "we'll email you" state.
     *
     * @return array<int, int>
     */
    private function subscribedVariantIds(): array
    {
        if (auth()->guest()) {
            return [];
        }

        return StockSubscription::query()
            ->where('user_id', auth()->id())
            ->whereIn('product_variant_id', $this->product->variants->pluck('id'))
            ->pluck('product_variant_id')
            ->all();
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
