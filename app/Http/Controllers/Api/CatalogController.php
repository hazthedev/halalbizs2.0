<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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
     * (Listing::SORTS minus relevance), min_sen/max_sen on variant price, store (slug).
     */
    public function products(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string'],
            'store' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in(['latest', 'top', 'price_asc', 'price_desc'])],
            'min_sen' => ['nullable', 'integer', 'min:0'],
            'max_sen' => ['nullable', 'integer', 'min:0'],
        ]);

        $minVariantPrice = ProductVariant::select('price_sen')
            ->whereColumn('product_id', 'products.id')
            ->orderBy('price_sen')
            ->limit(1);

        $products = Product::query()
            ->live()
            ->with(['variants', 'store', 'media'])
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

        match ($filters['sort'] ?? 'latest') {
            'top' => $products->orderByDesc('sold_count')->orderByDesc('products.id'),
            'price_asc' => $products->orderBy($minVariantPrice),
            'price_desc' => $products->orderByDesc($minVariantPrice),
            default => $products->orderByDesc('published_at')->orderByDesc('products.id'),
        };

        return ProductResource::collection($products->paginate(24));
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

        return new ProductResource($product->load(['variants', 'store', 'media']));
    }

    public function categories()
    {
        return CategoryResource::collection(
            Category::query()->where('is_active', true)->orderBy('position')->get(),
        );
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $ids = Product::searchKeywordIds($term);

        $products = Product::query()
            ->live()
            ->whereIn('id', $ids ?: [0])
            ->with(['variants', 'store', 'media'])
            ->get();

        return ProductResource::collection($products);
    }
}
