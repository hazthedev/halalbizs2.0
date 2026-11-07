<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CoinController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\WishlistController;
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
    Route::get('/products/{product:slug}/reviews', [CatalogController::class, 'reviews'])->name('api.product.reviews');
    Route::get('/home', HomeController::class)->name('api.home');
    Route::get('/stores/{store:slug}', [StoreController::class, 'show'])->name('api.stores.show');
    Route::get('/stores/{store:slug}/products', [StoreController::class, 'products'])->name('api.stores.products');
});

/*
| Mobile app API, slice 1 (mobile plan §4): Sanctum bearer tokens, the
| {ok,data,meta}/{ok,error} envelope from App\Http\Api\ApiResponse, errors
| shaped in bootstrap/app.php. Slice 2 adds cart, checkout, the payment
| hand-off, orders and the account extras; seller endpoints are a later slice.
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

        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('/cart/items/{variant}', [CartController::class, 'destroy'])->name('cart.items.destroy');
        Route::post('/cart/select-all', [CartController::class, 'selectAll'])->name('cart.select-all');

        Route::middleware('verified.api')->group(function () {
            Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
            Route::post('/checkout/place', [CheckoutController::class, 'place'])->middleware('throttle:api-checkout')->name('checkout.place');
        });

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/buckets', [OrderController::class, 'buckets'])->name('orders.buckets');
        Route::get('/return-reasons', [OrderController::class, 'returnReasons'])->name('orders.return-reasons');
        Route::get('/orders/{orderNo}/payment', [OrderController::class, 'payment'])->name('orders.payment');
        Route::get('/orders/{subOrder}', [OrderController::class, 'show'])->whereNumber('subOrder')->name('orders.show');
        Route::post('/orders/{subOrder}/cancel', [OrderController::class, 'cancel'])->whereNumber('subOrder')->name('orders.cancel');
        Route::post('/orders/{subOrder}/confirm-received', [OrderController::class, 'confirmReceived'])->whereNumber('subOrder')->name('orders.confirm-received');
        Route::post('/orders/{subOrder}/return', [OrderController::class, 'returnRequest'])->whereNumber('subOrder')->name('orders.return');
        Route::post('/orders/{subOrder}/review', [OrderController::class, 'review'])->whereNumber('subOrder')->middleware('throttle:api-review')->name('orders.review');

        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

        Route::get('/coins', [CoinController::class, 'show'])->name('coins.show');
        Route::post('/coins/check-in', [CoinController::class, 'checkIn'])->name('coins.check-in');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])->name('conversations.messages.store');
    });
});
