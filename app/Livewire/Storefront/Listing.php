<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SearchLog;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listing engine — one component, two entries (docs/05 §B2):
 * /c/{category:slug} (category browse) and /search?q= (Scout search).
 * Filters and sort sync to the query string; 24/page "Load more" pagination.
 */
#[Layout('layouts.storefront')]
class Listing extends Component
{
    use InteractsWithCart;

    public const PER_PAGE = 24;

    public const SORTS = ['relevance', 'latest', 'top', 'price_asc', 'price_desc'];

    /** Route-bound category (null on /search). */
    #[Locked]
    public ?Category $rootCategory = null;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $sort = '';

    /** Child category slug filter. */
    #[Url(as: 'category', except: '')]
    public string $childCategory = '';

    /** Price bounds in whole RM — converted to sen with * 100, never floats. */
    #[Url(as: 'price_min', except: null)]
    public ?int $priceMin = null;

    #[Url(as: 'price_max', except: null)]
    public ?int $priceMax = null;

    /** Minimum rating, 1–5. */
    #[Url(except: null)]
    public ?int $rating = null;

    /** Seller state (ships from). */
    #[Url(except: '')]
    public string $state = '';

    #[Url(except: false)]
    public bool $cod = false;

    public int $perPage = self::PER_PAGE;

    /** Per-request guard against duplicate consecutive search logs. */
    private ?string $loggedTerm = null;

    /** @var array<int>|null per-request cache of Scout result ids (relevance order) */
    private ?array $searchIdCache = null;

    public function mount(?Category $category = null): void
    {
        $this->rootCategory = $category;

        if ($this->isSearch()) {
            $this->logSearch();
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'sort', 'childCategory', 'priceMin', 'priceMax', 'rating', 'state', 'cod'], true)) {
            $this->perPage = self::PER_PAGE;
        }

        if ($property === 'q' && $this->isSearch()) {
            $this->searchIdCache = null;
            $this->logSearch();
        }
    }

    public function loadMore(): void
    {
        $this->perPage += self::PER_PAGE;
    }

    public function clearFilters(): void
    {
        $this->reset('childCategory', 'priceMin', 'priceMax', 'rating', 'state', 'cod');
        $this->perPage = self::PER_PAGE;
    }

    public function removeFilter(string $filter): void
    {
        match ($filter) {
            'category' => $this->reset('childCategory'),
            'price' => $this->reset('priceMin', 'priceMax'),
            'rating' => $this->reset('rating'),
            'state' => $this->reset('state'),
            'cod' => $this->reset('cod'),
            default => null,
        };

        $this->perPage = self::PER_PAGE;
    }

    public function render()
    {
        $query = $this->results();
        $total = (clone $query)->count();

        return view('livewire.storefront.listing', [
            'isSearch' => $this->isSearch(),
            'products' => $query->take($this->perPage)->get(),
            'total' => $total,
            'hasMore' => $total > $this->perPage,
            'effectiveSort' => $this->effectiveSort(),
            'breadcrumbs' => $this->breadcrumbs(),
            'children' => $this->rootCategory?->children()->where('is_active', true)->get() ?? collect(),
            'chips' => $this->activeChips(),
            'states' => Store::query()->approved()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
            'wishlistedIds' => $this->wishlistedIds(),
        ])->title($this->pageTitle());
    }

    private function isSearch(): bool
    {
        return $this->rootCategory === null;
    }

    private function hasSearchTerm(): bool
    {
        return $this->isSearch() && trim($this->q) !== '';
    }

    private function effectiveSort(): string
    {
        if (in_array($this->sort, self::SORTS, true) && ($this->sort !== 'relevance' || $this->hasSearchTerm())) {
            return $this->sort;
        }

        return $this->hasSearchTerm() ? 'relevance' : 'latest';
    }

    /** Filtered + sorted query for the current entry (search or category). */
    private function results(): Builder
    {
        $query = Product::query()->live()->with(['media', 'variants', 'store']);

        if ($this->isSearch()) {
            if ($this->hasSearchTerm()) {
                $query->whereIn('products.id', $this->searchIds());
            }
        } else {
            $query->whereIn('category_id', $this->rootCategory->descendantIds());
        }

        if ($this->childCategory !== '') {
            $child = Category::query()->where('slug', $this->childCategory)->first();

            if ($child !== null) {
                $query->whereIn('category_id', $child->descendantIds());
            }
        }

        if ($this->priceMin !== null || $this->priceMax !== null) {
            $query->whereHas('variants', function (Builder $variants) {
                if ($this->priceMin !== null) {
                    $variants->where('price_sen', '>=', max(0, $this->priceMin) * 100);
                }

                if ($this->priceMax !== null) {
                    $variants->where('price_sen', '<=', max(0, $this->priceMax) * 100);
                }
            });
        }

        if ($this->rating !== null && $this->rating >= 1 && $this->rating <= 5) {
            $query->where('rating_avg', '>=', $this->rating);
        }

        if ($this->state !== '') {
            $query->whereHas('store', fn (Builder $store) => $store->where('state', $this->state));
        }

        if ($this->cod) {
            $query->where('cod_enabled', true);
        }

        return $this->applySort($query);
    }

    private function applySort(Builder $query): Builder
    {
        $minVariantPrice = ProductVariant::select('price_sen')
            ->whereColumn('product_id', 'products.id')
            ->orderBy('price_sen')
            ->limit(1);

        return match ($this->effectiveSort()) {
            'top' => $query->orderByDesc('sold_count')->orderByDesc('products.id'),
            'price_asc' => $query->orderBy($minVariantPrice),
            'price_desc' => $query->orderByDesc($minVariantPrice),
            'relevance' => $this->applyRelevanceOrder($query),
            default => $query->orderByDesc('published_at')->orderByDesc('products.id'),
        };
    }

    /** Preserve Scout's result order: ORDER BY CASE id. */
    private function applyRelevanceOrder(Builder $query): Builder
    {
        $ids = $this->searchIds();

        if ($ids === []) {
            return $query;
        }

        $cases = collect($ids)
            ->map(fn ($id, $index) => 'WHEN '.(int) $id.' THEN '.(int) $index)
            ->implode(' ');

        return $query->orderByRaw("CASE products.id {$cases} END");
    }

    /** @return array<int> Scout result ids, relevance-ordered (cached per request). */
    private function searchIds(): array
    {
        return $this->searchIdCache ??= Product::search(trim($this->q))->keys()->all();
    }

    /** Log once per distinct executed search (search entry only). */
    private function logSearch(): void
    {
        $term = trim($this->q);

        if ($term === '' || $term === $this->loggedTerm) {
            return;
        }

        $this->loggedTerm = $term;

        SearchLog::create([
            'term' => $term,
            'results_count' => count($this->searchIds()),
            'user_id' => auth()->id(),
        ]);
    }

    /** @return array<int, Category> parent chain → current (category entry only) */
    private function breadcrumbs(): array
    {
        $crumbs = [];
        $node = $this->rootCategory;

        while ($node !== null) {
            array_unshift($crumbs, $node);
            $node = $node->parent;
        }

        return $crumbs;
    }

    /** @return array<string, string> applied filters as chip labels keyed by filter name */
    private function activeChips(): array
    {
        $chips = [];

        if ($this->childCategory !== '') {
            $child = Category::query()->where('slug', $this->childCategory)->first();
            $chips['category'] = $child?->getTranslation('name', app()->getLocale()) ?? $this->childCategory;
        }

        if ($this->priceMin !== null && $this->priceMax !== null) {
            $chips['price'] = __('RM:min – RM:max', ['min' => $this->priceMin, 'max' => $this->priceMax]);
        } elseif ($this->priceMin !== null) {
            $chips['price'] = __('From RM:min', ['min' => $this->priceMin]);
        } elseif ($this->priceMax !== null) {
            $chips['price'] = __('Up to RM:max', ['max' => $this->priceMax]);
        }

        if ($this->rating !== null) {
            $chips['rating'] = __('★ :rating & up', ['rating' => $this->rating]);
        }

        if ($this->state !== '') {
            $chips['state'] = $this->state;
        }

        if ($this->cod) {
            $chips['cod'] = __('COD available');
        }

        return $chips;
    }

    private function pageTitle(): string
    {
        if (! $this->isSearch()) {
            return $this->rootCategory->getTranslation('name', app()->getLocale());
        }

        $term = trim($this->q);

        return $term === '' ? __('Search') : __('Search: :term', ['term' => $term]);
    }
}
