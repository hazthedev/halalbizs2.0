<?php

namespace App\Http\Controllers\Api\Seller;

use App\Exceptions\ProductPublishBlockedException;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Base for /api/v1/seller/*: the store the `seller.api` middleware parked on
 * the request, and the 409 mapping for the services' state refusals. Every
 * row lookup goes through the store's relations, so another store's id is a
 * plain 404 (never a 403 that confirms it exists).
 */
abstract class SellerController extends Controller
{
    protected function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }

    /**
     * Run a service call; an InvalidArgumentException (illegal transition,
     * wrong state) becomes a 409 envelope carrying the service's own message,
     * and a halal-policy refusal a 409 `halal_blocked` with the policy's reason.
     */
    protected function attempt(Closure $action, string $code = 'invalid_transition'): JsonResponse
    {
        try {
            return $action();
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($code, $e->getMessage(), 409);
        } catch (ProductPublishBlockedException $e) {
            return ApiResponse::error('halal_blocked', $e->getMessage(), 409);
        }
    }
}
