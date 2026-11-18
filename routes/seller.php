<?php

use App\Http\Controllers\InvoiceController;
use App\Livewire\Seller;
use Illuminate\Support\Facades\Route;

// Seller centre — guarded by EnsureSeller (role + approved store).
Route::get('/', Seller\Dashboard::class)->name('dashboard');

Route::get('/products', Seller\Products\Index::class)->name('products.index');
Route::get('/products/create', Seller\Products\Form::class)->name('products.create');
Route::get('/products/import', Seller\Products\BulkImport::class)->name('products.import');
Route::get('/products/{product}/edit', Seller\Products\Form::class)->name('products.edit');

Route::get('/certificates', Seller\Certificates::class)->name('certificates');
Route::get('/certificates/create', Seller\Certificates\Form::class)->name('certificates.create');
Route::get('/certificates/{certificate}/edit', Seller\Certificates\Form::class)->name('certificates.edit');

Route::get('/orders', Seller\Orders\Index::class)->name('orders.index');
Route::get('/orders/{subOrder}', Seller\Orders\Detail::class)->name('orders.show');
Route::get('/orders/{subOrder}/packing-slip', [InvoiceController::class, 'seller'])->name('orders.packing-slip');

Route::get('/vouchers', Seller\Vouchers\Index::class)->name('vouchers.index');
Route::get('/vouchers/create', Seller\Vouchers\Form::class)->name('vouchers.create');
Route::get('/vouchers/{voucher}/edit', Seller\Vouchers\Form::class)->name('vouchers.edit');
Route::get('/group-buys', Seller\GroupBuys::class)->name('group-buys.index');
Route::get('/group-buys/create', Seller\GroupBuys\Form::class)->name('group-buys.create');
Route::get('/live', Seller\LiveSessions::class)->name('live.index');
Route::get('/live/create', Seller\LiveSessions\Form::class)->name('live.create');
Route::get('/live/{session}', Seller\LiveSessions\Detail::class)->name('live.show');
Route::get('/earnings', Seller\Earnings::class)->name('earnings');
Route::get('/reviews', Seller\Reviews\Index::class)->name('reviews.index');
Route::get('/questions', Seller\Questions\Index::class)->name('questions.index');
Route::get('/messages', Seller\Messages::class)->name('messages');
Route::get('/notifications', Seller\Notifications::class)->name('notifications');
Route::get('/boosts', Seller\Boosts::class)->name('boosts');

Route::get('/settings', Seller\Settings::class)->name('settings');
