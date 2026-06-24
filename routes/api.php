<?php

use App\Http\Controllers\Api\CatalogController;
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
