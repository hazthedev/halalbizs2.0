<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Api\Seller\EarningsController as SellerEarningsController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Api\Seller\StoreController as SellerStoreController;
use Illuminate\Support\Facades\Route;

/*
| Public read-first catalog API (M1.7). Prefixed /api, 'api' middleware group.
| No auth on reads; write/checkout endpoints stay on the atomic web services.
*/
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/products', [CatalogController::class, 'products'])->name('api.products');
    Route::get('/products/{product:slug}', [CatalogController::class, 'product'])->name('api.product');
    Route::get('/categories', [CatalogController::class, 'categories'])->name('api.categories');
    Route::get('/search', [CatalogController::class, 'search'])->name('api.search');
});

/*
| Mobile app API, slice 1 (mobile plan §4): Sanctum bearer tokens, the
| {ok,data,meta}/{ok,error} envelope from App\Http\Api\ApiResponse, errors
| shaped in bootstrap/app.php. Cart, checkout and buyer orders are later
| slices; the seller centre is slice 3 below.
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::get('/config', ConfigController::class)->middleware('throttle:api')->name('config');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api-register')->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login');
        Route::post('/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:api')->name('two-factor');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:api')->name('forgot-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/verification/resend', [AuthController::class, 'resendVerification'])->name('verification.resend');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::patch('/me', [MeController::class, 'update'])->name('me.update');
        Route::put('/me/password', [MeController::class, 'password'])->name('me.password');
        Route::delete('/me', [MeController::class, 'destroy'])->name('me.destroy');

        Route::apiResource('addresses', AddressController::class)->except('show');

        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        /*
        | Seller centre, slice 3 (mobile plan §4). `seller.api` = seller role +
        | approved store as a 403 envelope; `verified.api` mirrors the web
        | seller group's `verified`. Ids are plain ints resolved through the
        | store's relations, so another store's row is a 404.
        */
        Route::prefix('seller')->name('seller.')->middleware(['verified.api', 'seller.api'])->group(function () {
            Route::get('/dashboard', SellerDashboardController::class)->name('dashboard');

            Route::get('/couriers', [SellerOrderController::class, 'couriers'])->name('couriers');
            Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{subOrder}', [SellerOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{subOrder}/pack', [SellerOrderController::class, 'pack'])->name('orders.pack');
            Route::post('/orders/{subOrder}/ship', [SellerOrderController::class, 'ship'])->name('orders.ship');
            Route::post('/orders/{subOrder}/delivered', [SellerOrderController::class, 'delivered'])->name('orders.delivered');
            Route::post('/orders/{subOrder}/return/accept', [SellerOrderController::class, 'returnAccept'])->name('orders.return.accept');
            Route::post('/orders/{subOrder}/return/dispute', [SellerOrderController::class, 'returnDispute'])->name('orders.return.dispute');
            Route::post('/orders/{subOrder}/return/received', [SellerOrderController::class, 'returnReceived'])->name('orders.return.received');

            Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
            Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}', [SellerProductController::class, 'show'])->name('products.show');
            Route::patch('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
            Route::post('/products/{product}/images', [SellerProductController::class, 'images'])->name('products.images');
            Route::delete('/products/{product}/images/{media}', [SellerProductController::class, 'destroyImage'])->name('products.images.destroy');
            Route::post('/products/{product}/publish', [SellerProductController::class, 'publish'])->name('products.publish');
            Route::post('/products/{product}/delist', [SellerProductController::class, 'delist'])->name('products.delist');
            Route::post('/products/{product}/relist', [SellerProductController::class, 'relist'])->name('products.relist');

            Route::get('/earnings', [SellerEarningsController::class, 'index'])->name('earnings');
            Route::post('/payouts', [SellerEarningsController::class, 'requestPayout'])->name('payouts.store');

            Route::get('/store', [SellerStoreController::class, 'show'])->name('store.show');
            Route::patch('/store', [SellerStoreController::class, 'update'])->name('store.update');
            Route::post('/store/logo', [SellerStoreController::class, 'logo'])->name('store.logo');
            Route::post('/store/banner', [SellerStoreController::class, 'banner'])->name('store.banner');

            Route::get('/reviews', [SellerReviewController::class, 'index'])->name('reviews.index');
            Route::post('/reviews/{review}/reply', [SellerReviewController::class, 'reply'])->name('reviews.reply');
        });
    });
});
