<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The public store page (mobile plan §4) — approved stores only, like StorePage. */
class StoreController extends Controller
{
    public function show(Store $store): JsonResponse
    {
        abort_unless($store->isApproved(), 404);

        return ApiResponse::ok(new StoreResource($store));
    }

    /** Same sort keys as StorePage::SORTS. */
    public function products(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isApproved(), 404);

        $sort = $request->validate(['sort' => ['nullable', Rule::in(['latest', 'top', 'price_asc', 'price_desc'])]])['sort'] ?? 'latest';

        $minPriceSub = ProductVariant::query()
            ->selectRaw('min(price_sen)')
            ->whereColumn('product_variants.product_id', 'products.id');

        $products = $store->products()->live()
            ->with(['halalCertificate', 'variants', 'media', 'store'])
            ->when($sort === 'latest', fn ($query) => $query->orderByDesc('published_at'))
            ->when($sort === 'top', fn ($query) => $query->orderByDesc('sold_count'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy($minPriceSub))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc($minPriceSub))
            ->orderByDesc('products.id')
            ->paginate(24);

        return ApiResponse::ok(ProductResource::collection($products));
    }
}
