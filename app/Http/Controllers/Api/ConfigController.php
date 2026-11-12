<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use App\Settings\MobileAppSettings;
use App\Settings\SiteInfoSettings;
use App\Support\MalaysianStates;
use Illuminate\Http\JsonResponse;

/**
 * Public bootstrap payload for the mobile app (mobile plan §4): the flags and
 * lists the app needs before anyone logs in.
 */
class ConfigController extends Controller
{
    public function __invoke(GeneralSettings $general, CodSettings $cod, CartService $cart, MobileAppSettings $app, SiteInfoSettings $info): JsonResponse
    {
        $base = rtrim((string) config('app.url'), '/');

        return ApiResponse::ok([
            'purchasing_enabled' => $cart->purchasingEnabled(),
            'cod' => ['enabled' => $cod->enabled, 'max_order_sen' => $cod->max_order_sen],
            'locales' => $general->enabled_locales,
            'currencies' => $general->display_currencies,
            'states' => MalaysianStates::ALL,
            // Admin → Settings → Mobile app. The app blocks itself below the
            // minimum and shows the notice while maintenance is on.
            'min_app_version' => $app->min_version,
            'maintenance' => [
                'enabled' => $app->maintenance_enabled,
                'message' => $app->maintenanceMessage(app()->getLocale()),
            ],
            // Admin → Content → Footer & contact. Empty = null.
            'contact' => [
                'email' => $info->contact_email ?: null,
                'phone' => $info->contact_phone ?: null,
                'whatsapp' => $info->contact_whatsapp ?: null,
                'address' => $info->contact_address ?: null,
            ],
            'social' => [
                'facebook' => $info->social_facebook ?: null,
                'instagram' => $info->social_instagram ?: null,
                'tiktok' => $info->social_tiktok ?: null,
                'youtube' => $info->social_youtube ?: null,
                'x' => $info->social_x ?: null,
            ],
            'urls' => [
                'privacy' => $base.'/page/privacy',
                'terms' => $base.'/page/terms',
                'seller_apply' => $base.'/seller/apply',
            ],
        ]);
    }
}
