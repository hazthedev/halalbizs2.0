<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HomeLayout;
use Illuminate\Http\JsonResponse;

/**
 * The app's home tab: the same layout the web home draws (HomeLayout), as
 * data. `blocks` is the admin's section order; featured / categories /
 * flash_sale / banners are the older keys, kept for older app builds.
 */
class HomeController extends Controller
{
    public function __invoke(HomeLayout $layout): JsonResponse
    {
        return ApiResponse::ok($layout->forApp(app()->getLocale()));
    }
}
