<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Api\ApiResponse;
use App\Http\Resources\SellerOrderResource;
use App\Services\SellerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends SellerController
{
    public function __invoke(Request $request, SellerDashboardService $dashboard): JsonResponse
    {
        $stats = $dashboard->stats($this->currentStore($request));

        $stats['recent_orders'] = SellerOrderResource::collection($stats['recent_orders'])->resolve();

        return ApiResponse::ok($stats);
    }
}
