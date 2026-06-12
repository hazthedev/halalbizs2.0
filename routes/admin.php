<?php

use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

// Admin panel — guarded by EnsureAdmin; sections gated by spatie permissions.
Route::get('/', Admin\Dashboard::class)->name('dashboard');

Route::middleware('can:sellers.manage')->group(function () {
    Route::get('/sellers/applications', Admin\Sellers\Applications::class)->name('sellers.applications');
    Route::get('/sellers/stores', Admin\Sellers\Stores::class)->name('sellers.stores');
    Route::get('/sellers/stores/{store}', Admin\Sellers\StoreDetail::class)->name('sellers.stores.show');
    Route::get('/buyers', Admin\Buyers\Index::class)->name('buyers.index');
    Route::get('/buyers/{user}', Admin\Buyers\Detail::class)->name('buyers.show');
});

Route::middleware('can:products.moderate')->group(function () {
    Route::get('/catalog/categories', Admin\Catalog\Categories::class)->name('catalog.categories');
    Route::get('/catalog/attributes', Admin\Catalog\Attributes::class)->name('catalog.attributes');
    Route::get('/catalog/brands', Admin\Catalog\Brands::class)->name('catalog.brands');
    Route::get('/catalog/moderation', Admin\Catalog\Moderation::class)->name('catalog.moderation');
});

Route::middleware('can:orders.manage')->group(function () {
    Route::get('/orders', Admin\Orders\Index::class)->name('orders.index');
    Route::get('/orders/{subOrder}', Admin\Orders\Detail::class)->name('orders.show');
    Route::get('/payments', Admin\Orders\Payments::class)->name('payments.index');
});

Route::middleware('can:finance.manage')->group(function () {
    Route::get('/finance/commission', Admin\Finance\Commission::class)->name('finance.commission');
    Route::get('/finance/payouts', Admin\Finance\Payouts::class)->name('finance.payouts');
});

Route::middleware('can:cms.manage')->group(function () {
    Route::get('/content/banners', Admin\Content\Banners::class)->name('content.banners');
    Route::get('/content/home-sections', Admin\Content\HomeSections::class)->name('content.home-sections');
    Route::get('/content/pages', Admin\Content\Pages::class)->name('content.pages');
});

Route::middleware('can:vouchers.manage')->group(function () {
    Route::get('/content/vouchers', Admin\Content\Vouchers::class)->name('content.vouchers');
});

Route::middleware('can:localization.manage')->group(function () {
    Route::get('/localization', Admin\Localization\Index::class)->name('localization');
});

Route::middleware('can:settings.manage')->group(function () {
    Route::get('/system/settings', Admin\System\Settings::class)->name('system.settings');
    Route::get('/system/staff', Admin\System\Staff::class)->name('system.staff');
    Route::get('/system/audit', Admin\System\AuditLog::class)->name('system.audit');
});
