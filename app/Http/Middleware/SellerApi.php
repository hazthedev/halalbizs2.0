<?php

namespace App\Http\Middleware;

use App\Enums\StoreStatus;
use App\Http\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API twin of EnsureSeller (mobile plan §4): the seller role AND an approved
 * store, as a 403 envelope instead of a redirect. Alias `seller.api`. The
 * store is parked on the request so every seller controller scopes by it.
 */
class SellerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $user?->store;

        if ($store !== null && $store->status === StoreStatus::Suspended) {
            return ApiResponse::error('store_suspended', __('Your store is suspended. Contact support.'), 403);
        }

        if ($user === null || $store === null || ! $user->hasRole('seller') || ! $store->isApproved()) {
            return ApiResponse::error('seller_required', __('An approved seller account is required.'), 403);
        }

        $request->attributes->set('store', $store);

        return $next($request);
    }
}
