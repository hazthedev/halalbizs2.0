<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CoinController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\LiveController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Api\Seller\EarningsController as SellerEarningsController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Api\Seller\StoreController as SellerStoreController;
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
    Route::get('/products/{product:slug}/questions', [CatalogController::class, 'questions'])->name('api.product.questions');
    Route::get('/products/{product:slug}/related', [CatalogController::class, 'related'])->name('api.product.related');
    Route::get('/recommended', [CatalogController::class, 'recommended'])->name('api.recommended');
    Route::get('/certificates/{number}', [CertificateController::class, 'show'])->name('api.certificates.show');
    // etag: an unchanged home answers 304 with no body (CMS item 10).
    Route::get('/home', HomeController::class)->middleware('cache.headers:etag')->name('api.home');
    // CMS item 5: pages and help as data, for native app screens with an offline copy.
    Route::get('/pages', [ContentController::class, 'pages'])->name('api.pages');
    Route::get('/pages/{slug}', [ContentController::class, 'page'])->name('api.pages.show');
    Route::get('/help', [ContentController::class, 'help'])->name('api.help');
    Route::get('/help/{article}', [ContentController::class, 'helpArticle'])->whereNumber('article')->name('api.help.show');
    Route::get('/live', [LiveController::class, 'index'])->name('api.live');
    Route::get('/live/{session:slug}', [LiveController::class, 'show'])->name('api.live.show');
    Route::get('/stores/{store:slug}', [StoreController::class, 'show'])->name('api.stores.show');
    Route::get('/stores/{store:slug}/products', [StoreController::class, 'products'])->name('api.stores.products');
});

/*
| Mobile app API, slice 1 (mobile plan §4): Sanctum bearer tokens, the
| {ok,data,meta}/{ok,error} envelope from App\Http\Api\ApiResponse, errors
| shaped in bootstrap/app.php. Slice 2 adds cart, checkout, the payment
| hand-off, orders and the account extras; slice 3 is the seller centre below.
*/
Route::prefix('v1')->name('api.')->group(function () {
    Route::get('/config', ConfigController::class)->middleware('throttle:api')->name('config');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api-register')->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login');
        Route::post('/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:api')->name('two-factor');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware(['throttle:api', 'throttle:api-forgot'])->name('forgot-password');

        Route::middleware(['auth:sanctum', 'suspended.api', 'throttle:api'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            // throttle:5,60 keys on the user id: five verification mails per hour, as Profile's phone codes.
            Route::post('/verification/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:5,60')->name('verification.resend');
        });
    });

    Route::middleware(['auth:sanctum', 'suspended.api', 'throttle:api'])->group(function () {
        Route::post('/products/{product:slug}/questions', [CatalogController::class, 'ask'])->name('product.questions.ask');
        Route::get('/me', [MeController::class, 'show'])->name('me.show');
        Route::patch('/me', [MeController::class, 'update'])->name('me.update');
        // Both take the current password: five guesses a minute per user (throttle keys on the user id).
        Route::put('/me/password', [MeController::class, 'password'])->middleware('throttle:5,1,sensitive')->name('me.password');
        Route::delete('/me', [MeController::class, 'destroy'])->middleware('throttle:5,1,sensitive')->name('me.destroy');
        Route::post('/me/web-sign-in-link', [MeController::class, 'webSignInLink'])->middleware('throttle:5,1,sensitive')->name('me.web-sign-in-link');

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
        Route::get('/orders/quick-status', [OrderController::class, 'quickStatus'])->name('orders.quick-status');
        Route::get('/return-reasons', [OrderController::class, 'returnReasons'])->name('orders.return-reasons');
        Route::get('/orders/{orderNo}/payment', [OrderController::class, 'payment'])->name('orders.payment');
        Route::get('/orders/{subOrder}', [OrderController::class, 'show'])->whereNumber('subOrder')->name('orders.show');
        Route::post('/orders/{subOrder}/cancel', [OrderController::class, 'cancel'])->whereNumber('subOrder')->name('orders.cancel');
        Route::post('/orders/{subOrder}/confirm-received', [OrderController::class, 'confirmReceived'])->whereNumber('subOrder')->name('orders.confirm-received');
        Route::post('/orders/{subOrder}/return', [OrderController::class, 'returnRequest'])->whereNumber('subOrder')->name('orders.return');
        Route::post('/orders/{subOrder}/review', [OrderController::class, 'review'])->whereNumber('subOrder')->middleware('throttle:api-review')->name('orders.review');

        Route::post('/stores/{store:slug}/follow', [StoreController::class, 'follow'])->middleware('throttle:30,1,follow')->name('stores.follow');
        Route::delete('/stores/{store:slug}/follow', [StoreController::class, 'unfollow'])->middleware('throttle:30,1,follow')->name('stores.unfollow');
        Route::get('/me/followed-stores', [StoreController::class, 'followed'])->name('me.followed-stores');

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
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:api-chat')->name('conversations.messages.store');
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
