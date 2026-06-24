<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Read-first public catalog API (docs/ROADMAP.md M1.7). Reuses the same models
 * + Scout search as the storefront — no parallel data path. The substrate for
 * a native app, partner feeds and the future AI concierge tools.
 */
class CatalogController extends Controller
{
    public function products()
    {
        $products = Product::query()
            ->live()
            ->with(['variants', 'store', 'media'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(24);

        return ProductResource::collection($products);
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
