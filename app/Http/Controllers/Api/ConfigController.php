<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use App\Support\MalaysianStates;
use Illuminate\Http\JsonResponse;

/**
 * Public bootstrap payload for the mobile app (mobile plan §4): the flags and
 * lists the app needs before anyone logs in.
 */
class ConfigController extends Controller
{
    public function __invoke(GeneralSettings $general, CodSettings $cod, CartService $cart): JsonResponse
    {
        $base = rtrim((string) config('app.url'), '/');

        return ApiResponse::ok([
            'purchasing_enabled' => $cart->purchasingEnabled(),
            'cod' => ['enabled' => $cod->enabled, 'max_order_sen' => $cod->max_order_sen],
            'locales' => $general->enabled_locales,
            'currencies' => $general->display_currencies,
            'states' => MalaysianStates::ALL,
            'min_app_version' => '1.0.0',
            'urls' => [
                'privacy' => $base.'/page/privacy',
                'terms' => $base.'/page/terms',
                'seller_apply' => $base.'/seller/apply',
            ],
        ]);
    }
}
