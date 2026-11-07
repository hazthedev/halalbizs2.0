<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeController;
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
| shaped in bootstrap/app.php. Cart, checkout, orders and seller endpoints are
| later slices.
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
    });
});
