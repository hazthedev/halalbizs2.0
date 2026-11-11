<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\PublicStoreResource;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\StoreFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The public store page (mobile plan §4) — approved stores only, like StorePage. */
class StoreController extends Controller
{
    public function show(Store $store): JsonResponse
    {
        abort_unless($store->isApproved(), 404);

        return ApiResponse::ok(new PublicStoreResource($store));
    }

    /** Follow (idempotent). 403 for the owner's own store; the web FollowButton's rule set. */
    public function follow(Request $request, Store $store, StoreFollowService $follows): JsonResponse
    {
        abort_unless($store->isApproved(), 404);

        if (! $follows->follow($request->user(), $store)) {
            return ApiResponse::error('own_store', __('You cannot follow your own shop.'), 403);
        }

        return ApiResponse::ok($this->state($store, true, $follows));
    }

    /** Unfollow (idempotent). */
    public function unfollow(Request $request, Store $store, StoreFollowService $follows): JsonResponse
    {
        $follows->unfollow($request->user(), $store);

        return ApiResponse::ok($this->state($store, false, $follows));
    }

    /** The caller's followed shops, newest follow first, 20 a page. */
    public function followed(Request $request, StoreFollowService $follows): JsonResponse
    {
        $stores = $follows->followedStores($request->user());
        $stores->setCollection($stores->getCollection()->map(fn (Store $store) => [
            'id' => $store->id,
            'slug' => $store->slug,
            'name' => $store->name,
            'logo' => $store->getFirstMediaUrl('logo', 'thumb') ?: null,
            'followers_count' => $follows->followersCount($store),
        ]));

        return ApiResponse::ok($stores);
    }

    private function state(Store $store, bool $following, StoreFollowService $follows): array
    {
        return ['is_following' => $following, 'followers_count' => $follows->followersCount($store)];
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
