<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Livewire\Storefront\Listing;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-first public catalog API (docs/ROADMAP.md M1.7). Reuses the same models
 * + Scout search as the storefront — no parallel data path. The substrate for
 * a native app, partner feeds and the future AI concierge tools.
 */
class CatalogController extends Controller
{
    /**
     * Filters mirror the web Listing: category (slug, with descendants), sort
     * (Listing::SORTS minus relevance), min_sen/max_sen on variant price, store (slug),
     * certifier[] (JAKIM/MUIS/BPJPH/ESMA) and assure[] (valid12/dedicated/export).
     */
    public function products(Request $request)
    {
        $filters = $this->filters($request, ['latest', 'top', 'price_asc', 'price_desc']);

        $products = $this->filtered(Product::query()->live()->with(['variants', 'store', 'media', 'halalCertificate']), $filters);
        $this->sort($products, $filters['sort'] ?? 'latest');

        return ProductResource::collection($products->paginate(24));
    }

    /** @param  list<string>  $sorts */
    private function filters(Request $request, array $sorts): array
    {
        return $request->validate([
            'category' => ['nullable', 'string'],
            'store' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in($sorts)],
            'min_sen' => ['nullable', 'integer', 'min:0'],
            'max_sen' => ['nullable', 'integer', 'min:0'],
            // The web Listing's halal facets: certifying body + assurance.
            'certifier' => ['nullable', 'array'],
            'certifier.*' => ['string', Rule::in(Listing::certifierCodes())],
            'assure' => ['nullable', 'array'],
            'assure.*' => ['string', Rule::in(Listing::ASSURANCES)],
        ]);
    }

    private function filtered(Builder $products, array $filters): Builder
    {
        return $products
            ->halalFacets($filters['certifier'] ?? [], $filters['assure'] ?? [])
            ->when($filters['category'] ?? null, function ($query, string $slug) {
                $category = Category::query()->where('slug', $slug)->firstOrFail();

                $query->whereIn('category_id', $category->descendantIds());
            })
            ->when($filters['store'] ?? null, fn ($query, string $slug) => $query->whereHas('store', fn ($store) => $store->where('slug', $slug)))
            ->when(isset($filters['min_sen']) || isset($filters['max_sen']), fn ($query) => $query->whereHas('variants', function ($variants) use ($filters) {
                if (isset($filters['min_sen'])) {
                    $variants->where('price_sen', '>=', (int) $filters['min_sen']);
                }

                if (isset($filters['max_sen'])) {
                    $variants->where('price_sen', '<=', (int) $filters['max_sen']);
                }
            }));
    }

    /**
     * Same orders as the web Listing::applySort(); relevance keeps the
     * keyword-search order in $ids.
     *
     * @param  list<int>  $ids
     */
    private function sort(Builder $products, string $sort, array $ids = []): void
    {
        $minVariantPrice = ProductVariant::select('price_sen')
            ->whereColumn('product_id', 'products.id')
            ->orderBy('price_sen')
            ->limit(1);

        match ($sort) {
            'top' => $products->orderByDesc('sold_count')->orderByDesc('products.id'),
            'price_asc' => $products->orderBy($minVariantPrice),
            'price_desc' => $products->orderByDesc($minVariantPrice),
            'relevance' => $ids === [] ? null : $products->orderByRaw('CASE products.id '
                .collect($ids)->map(fn ($id, $i) => 'WHEN '.(int) $id.' THEN '.(int) $i)->implode(' ').' END'),
            default => $products->orderByDesc('published_at')->orderByDesc('products.id'),
        };
    }

    /** Visible reviews, newest first — the PDP reviews tab without its filters. */
    public function reviews(Product $product): JsonResponse
    {
        abort_unless($product->isLive(), 404);

        $reviews = $product->reviews()->visible()
            ->with(['user', 'media'])
            ->latest()
            ->latest('id')
            ->paginate(10);

        return ApiResponse::ok(ReviewResource::collection($reviews));
    }

    public function product(Product $product)
    {
        abort_unless($product->isLive(), 404);

        return new ProductResource($product->load(['variants', 'store', 'media', 'options.values', 'halalCertificate', 'metafields']), detail: true);
    }

    public function categories()
    {
        return CategoryResource::collection(
            Category::query()->where('is_active', true)->orderBy('position')->get(),
        );
    }

    /** Keyword search + the same filters/sorts as products (default relevance). */
    public function search(Request $request)
    {
        $filters = $this->filters($request, ['relevance', 'latest', 'top', 'price_asc', 'price_desc']);
        $term = trim((string) $request->query('q', ''));
        $ids = Product::searchKeywordIds($term);

        $products = $this->filtered(
            Product::query()->live()->whereIn('products.id', $ids ?: [0])->with(['variants', 'store', 'media', 'halalCertificate']),
            $filters,
        );
        $this->sort($products, $filters['sort'] ?? 'relevance', $ids);

        return ProductResource::collection($products->get());
    }
}
