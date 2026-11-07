<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request, WishlistService $wishlist): JsonResponse
    {
        return ApiResponse::ok(ProductResource::collection($wishlist->products($request->user())));
    }

    public function toggle(Request $request, Product $product, WishlistService $wishlist): JsonResponse
    {
        return ApiResponse::ok(['wishlisted' => $wishlist->toggle($request->user(), $product)]);
    }
}
