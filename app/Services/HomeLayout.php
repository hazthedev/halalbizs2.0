<?php

namespace App\Services;

use App\Enums\BoostStatus;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\ScheduledLayout;
use App\Models\Store;
use App\Models\ThemeAsset;
use App\Settings\BoostSettings;
use App\Settings\ThemeSettings;
use App\Support\AppLinks;
use App\Support\ContentLocales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;

/**
 * The ONE reader of the admin's home layout (Admin → Content → Home sections,
 * Banners, Theme). The web home (Livewire Storefront\Home) draws sections()
 * as Blade; the app draws forApp() as native widgets. A rule about what the
 * home shows (order, on/off, empty sections, date windows) is written here
 * once, so the two screens cannot drift apart again.
 */
class HomeLayout
{
    /** Seconds the app's home payload is kept; flush() clears it on every content save. */
    public const CACHE_TTL = 300;

    /** Section types that render themselves (their data is not loaded here). */
    private const SELF_CONTAINED = ['recently_viewed', 'recommended'];

    /**
     * Active sections in admin order, each with its data. Empty sections
     * disappear, except the self-contained ones. $for ('web' or 'app') drops
     * sections the admin set to the other side only.
     *
     * @param  array<int, int>  $recentlyViewedIds
     * @return BaseCollection<int, array{section: HomeSection, data: Collection}>
     */
    public function sections(array $recentlyViewedIds = [], string $for = 'web'): BaseCollection
    {
        $other = $for === 'web' ? 'app' : 'web';
        $all = app(ContentDrafts::class)->visible(HomeSection::class, ['media'])
            ->reject(fn (HomeSection $section) => $section->show_on === $other);

        $rows = fn (?int $layoutId) => $all
            ->filter(fn (HomeSection $section) => $section->scheduled_layout_id === $layoutId)
            ->map(fn (HomeSection $section) => [
                'section' => $section,
                'data' => $this->sectionData($section, $recentlyViewedIds),
            ])
            ->reject(fn (array $row) => $row['data']->isEmpty()
                && ! in_array($row['section']->type, self::SELF_CONTAINED, true))
            ->values()
            ->toBase();

        $layoutId = $this->layoutId();
        $scheduled = $layoutId === null ? null : $rows($layoutId);

        // A scheduled layout with nothing to show never blanks the home.
        return $scheduled === null || $scheduled->isEmpty() ? $rows(null) : $scheduled;
    }

    /**
     * The scheduled layout showing now; null = the everyday layout. Picked by
     * the clock on every read, so the switch on and back needs no scheduler.
     * A staff preview can ask for one ahead of time with ?layout=<id>.
     */
    public function layoutId(): ?int
    {
        $asked = (int) request()->query('layout');

        return app(ContentDrafts::class)->previewing && $asked > 0
            ? ScheduledLayout::whereKey($asked)->value('id')
            : ScheduledLayout::current()?->id;
    }

    /** Occasion hero image URL: only when enabled, in window, and uploaded. */
    public function heroUrl(ThemeSettings $theme): ?string
    {
        if (! $theme->heroActive()) {
            return null;
        }

        $asset = ThemeAsset::where('key', 'hero')->first();
        $drafts = app(ContentDrafts::class);
        $url = ($drafts->previewing && $asset !== null ? $drafts->preview($asset) : $asset)?->getFirstMediaUrl('image', 'card');

        return $url !== null && $url !== '' ? $url : null;
    }

    /** @return Collection<int, Product> */
    public function products(string $source, int $limit): Collection
    {
        // Client-callable through the API: clamp to the range the admin form allows (HomeSections: 1..48).
        $limit = max(1, min($limit, 48));

        // 'top' leads with actively boosted products (flagged Sponsored),
        // then fills the remaining slots organically by sold_count.
        $boosted = $source === 'top' ? $this->boostedProducts($limit) : new Collection;

        $query = Product::live()->with(['halalCertificate', 'media', 'variants', 'store']);

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
     * The app's home tab as plain data, cached per language. Old keys
     * (featured, categories, flash_sale, banners) stay for app builds that
     * predate `blocks`.
     *
     * @return array<string, mixed>
     */
    public function forApp(string $locale): array
    {
        $build = function () use ($locale) {
            $rows = $this->sections(for: 'app');
            $theme = app(ThemeSettings::class);
            $heroUrl = $this->heroUrl($theme);

            $featured = $rows->first(fn (array $row) => in_array($row['section']->type, ['product_carousel', 'product_grid'], true));

            // Through JSON so the cached copy is plain arrays on every cache store.
            return json_decode(json_encode([
                'blocks' => $rows->map(fn (array $row) => $this->block($row['section'], $row['data'], $locale))->all(),
                'announcement' => $theme->announcementActive() ? [
                    'text' => $theme->announcementText($locale),
                    'bg' => $theme->announcement_bg,
                    'text_color' => $theme->announcement_text_color,
                ] : null,
                'hero' => $heroUrl === null ? null : ['image' => $heroUrl, 'occasion' => $theme->occasion],
                'featured' => ProductResource::collection($featured ? $featured['data'] : $this->products('top', 12))->resolve(),
                'categories' => CategoryResource::collection(
                    Category::active()->whereNull('parent_id')->with('media')->orderBy('position')->get()
                )->resolve(),
                'flash_sale' => $this->flashSale(),
                'banners' => $this->banners(app(ContentDrafts::class)->visible(Banner::class, ['media']), $locale),
            ]), true);
        };

        // A staff preview is one person's draft: it neither reads nor feeds the shoppers' cache.
        if (app(ContentDrafts::class)->previewing) {
            return $build();
        }

        // The cached copy never outlives the next start or end of a scheduled layout.
        $ttl = max(1, min(self::CACHE_TTL, ScheduledLayout::secondsToNextSwitch() ?? self::CACHE_TTL));

        return Cache::remember(self::cacheKey($locale), $ttl, $build);
    }

    /** Called on every save of home content (AppServiceProvider), so admins see their change at once. */
    public static function flush(): void
    {
        foreach (ContentLocales::ALL as $locale) {
            Cache::forget(self::cacheKey($locale));
        }
    }

    private static function cacheKey(string $locale): string
    {
        return 'home-layout:app:'.$locale;
    }

    /** One language of a {en, ms, vi} text, English when that language is empty. */
    public static function localized(?array $texts, string $locale): string
    {
        return (string) (($texts[$locale] ?? '') ?: ($texts['en'] ?? ''));
    }

    /** @param  array<int, int>  $recentlyViewedIds */
    private function sectionData(HomeSection $section, array $recentlyViewedIds): BaseCollection
    {
        return match ($section->type) {
            'banner' => app(ContentDrafts::class)->visible(Banner::class, ['media']),
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
            'recently_viewed' => $this->recentlyViewedProducts($recentlyViewedIds),
            // Hand-picked: the admin's order; anything no longer on sale or approved drops out.
            'picked_products' => $this->picked(
                Product::live()->with(['halalCertificate', 'media', 'variants', 'store']),
                $section->payload['product_ids'] ?? [],
            ),
            'picked_stores' => $this->picked(Store::approved()->with('media'), $section->payload['store_ids'] ?? []),
            'notice' => collect(array_filter([$section->payload['text'] ?? null], fn ($text) => ($text['en'] ?? '') !== '')),
            'picture_tiles' => collect($section->payload['tiles'] ?? [])
                ->map(fn (array $tile) => $tile + ['image' => $section->getFirstMediaUrl('tile_'.$tile['slot'], 'tile')])
                ->filter(fn (array $tile) => $tile['image'] !== '')
                ->values(),
            default => new Collection,
        };
    }

    /** @param  array<int, int>  $ids */
    private function picked(Builder $query, array $ids): BaseCollection
    {
        $found = $query->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $found->get($id))->filter()->values();
    }

    /**
     * One section as the app reads it. An app skips a `type` it does not
     * know, so a new type never breaks an older build.
     *
     * @return array<string, mixed>
     */
    private function block(HomeSection $section, BaseCollection $data, string $locale): array
    {
        $block = [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->getTranslation('title', $locale),
        ];

        return $block + match ($section->type) {
            'banner' => ['banners' => $this->banners($data, $locale)],
            'category_grid' => ['categories' => $data->map(fn (Category $category) => (new CategoryResource($category))->resolve() + [
                'image' => $category->getFirstMediaUrl('image', 'thumb') ?: null,
            ])->values()->all()],
            'product_carousel', 'product_grid' => [
                'source' => $section->payload['source'] ?? ($section->type === 'product_grid' ? 'top' : 'latest'),
                'products' => ProductResource::collection($data)->resolve(),
            ],
            'picked_products' => ['products' => ProductResource::collection($data)->resolve()],
            'picked_stores' => ['stores' => $data->map(fn (Store $store) => [
                'id' => $store->id,
                'slug' => $store->slug,
                'name' => $store->name,
                'logo' => $store->getFirstMediaUrl('logo', 'thumb') ?: null,
            ])->all()],
            'notice' => ['text' => self::localized($data->first(), $locale)],
            'picture_tiles' => ['tiles' => $data->map(fn (array $tile) => [
                'image' => $tile['image'],
                'label' => self::localized($tile['label'] ?? [], $locale) ?: null,
                'link_url' => $tile['link_url'] ?? null,
                'app_url' => AppLinks::fromWebUrl($tile['link_url'] ?? null),
            ])->all()],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function banners(Collection $banners, string $locale): array
    {
        return $banners->map(fn (Banner $banner) => [
            'id' => $banner->id,
            'title' => $banner->getTranslation('title', $locale),
            'subtitle' => $banner->getTranslation('subtitle', $locale),
            'cta_label' => $banner->getTranslation('cta_label', $locale),
            'link_url' => $banner->link_url,
            'app_url' => AppLinks::fromWebUrl($banner->link_url),
            'image' => $banner->imageUrl($locale) ?: null,
        ])->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function flashSale(): ?array
    {
        $items = FlashSaleItem::query()
            ->whereHas('flashSale', fn ($sale) => $sale->live())
            ->with(['variant.product.media', 'variant.product.store', 'variant.product.variants', 'variant.product.halalCertificate'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FlashSaleItem $item) => $item->variant?->product?->isLive())
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'ends_at' => Carbon::parse(FlashSale::query()->live()->min('ends_at'))->toIso8601String(),
            'products' => $items->map(fn (FlashSaleItem $item) => (new ProductResource($item->variant->product))->resolve() + [
                'flash' => [
                    'variant_id' => $item->product_variant_id,
                    'price_sen' => (int) $item->promo_price_sen,
                    'remaining' => $item->remaining(),
                    'percent_claimed' => $item->percentClaimed(),
                ],
            ])->values()->all(),
        ];
    }

    /**
     * Actively boosted live products, newest boost first, flagged for the
     * neutral Sponsored badge on the card.
     *
     * @return Collection<int, Product>
     */
    private function boostedProducts(int $limit): Collection
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
            ->with(['halalCertificate', 'media', 'variants', 'store'])
            ->whereHas('boosts', fn (Builder $boosts) => $boosts->active())
            ->orderByDesc($latestBoostStart)
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->each(fn (Product $product) => $product->sponsored = true);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function recentlyViewedProducts(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Product::live()
            ->with(['halalCertificate', 'media', 'variants', 'store'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids))
            ->values();
    }
}
