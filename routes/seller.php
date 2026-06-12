<?php

use App\Http\Controllers\InvoiceController;
use App\Livewire\Seller;
use Illuminate\Support\Facades\Route;

// Seller centre — guarded by EnsureSeller (role + approved store).
Route::get('/', Seller\Dashboard::class)->name('dashboard');

Route::get('/products', Seller\Products\Index::class)->name('products.index');
Route::get('/products/create', Seller\Products\Form::class)->name('products.create');
Route::get('/products/{product}/edit', Seller\Products\Form::class)->name('products.edit');

Route::get('/orders', Seller\Orders\Index::class)->name('orders.index');
Route::get('/orders/{subOrder}', Seller\Orders\Detail::class)->name('orders.show');
Route::get('/orders/{subOrder}/packing-slip', [InvoiceController::class, 'seller'])->name('orders.packing-slip');

Route::get('/settings', Seller\Settings::class)->name('settings');
