<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PreferenceController;
use App\Livewire\Seller\ApplicationStatus;
use App\Livewire\Seller\Apply;
use App\Livewire\Storefront;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ===== Storefront =====
Route::get('/', Storefront\Home::class)->name('home');
Route::get('/c/{category:slug}', Storefront\Listing::class)->name('category.show');
Route::get('/search', Storefront\Listing::class)->name('search');
Route::get('/p/{product:slug}', Storefront\ProductDetail::class)->name('product.show');
Route::get('/s/{store:slug}', Storefront\StorePage::class)->name('store.show');
Route::get('/cart', Storefront\CartPage::class)->name('cart');
Route::get('/page/{slug}', Storefront\StaticPage::class)->name('page.show');

// ===== Preferences & newsletter =====
Route::post('/preferences/locale', [PreferenceController::class, 'locale'])->name('preferences.locale');
Route::post('/preferences/currency', [PreferenceController::class, 'currency'])->name('preferences.currency');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

// ===== Auth =====
Route::middleware('guest')->group(function () {
    Route::get('/login', Storefront\Auth\Login::class)->name('login');
    Route::get('/register', Storefront\Auth\Register::class)->name('register');
    Route::get('/forgot-password', Storefront\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', Storefront\Auth\ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', Storefront\Auth\VerifyEmailNotice::class)->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('home');
    })->middleware('signed')->name('verification.verify');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

// ===== Checkout =====
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout', Storefront\Checkout::class)->name('checkout');
    Route::get('/checkout/success/{order:order_no}', Storefront\CheckoutSuccess::class)->name('checkout.success');
});

// ===== Buyer account =====
Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', Storefront\Account\Profile::class)->name('profile');
    Route::get('/addresses', Storefront\Account\Addresses::class)->name('addresses');
    Route::get('/wishlist', Storefront\Account\WishlistPage::class)->name('wishlist');
    Route::get('/notifications', Storefront\Account\Notifications::class)->name('notifications');
    Route::get('/orders', Storefront\Account\Orders::class)->name('orders');
    Route::get('/orders/{subOrder}', Storefront\Account\OrderDetail::class)->name('orders.show');
    Route::get('/orders/{subOrder}/invoice', [InvoiceController::class, 'buyer'])->name('orders.invoice');
});

// ===== Seller application (outside the approved-seller group) =====
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/seller/apply', Apply::class)->name('seller.apply');
    Route::get('/seller/status', ApplicationStatus::class)->name('seller.status');
});
