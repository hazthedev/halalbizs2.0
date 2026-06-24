<?php

use App\Enums\PaymentStatus;
use App\Http\Controllers\AffiliateReferralController;
use App\Http\Controllers\EasyParcelWebhookController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Ipay88Controller;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PreferenceController;
use App\Livewire\Seller\ApplicationStatus;
use App\Livewire\Seller\Apply;
use App\Livewire\Storefront;
use App\Models\Order;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ===== Store subdomains ({slug}.halalbizs2.0.test) =====
// Registered FIRST: the plain '/' route below has no host constraint and
// would otherwise swallow subdomain requests.
Route::domain('{store:slug}.'.config('app.store_subdomain_base'))->group(function () {
    Route::get('/', Storefront\StorePage::class)->name('store.subdomain');
});

// ===== Storefront =====
Route::get('/', Storefront\Home::class)->name('home');
Route::get('/c/{category:slug}', Storefront\Listing::class)->name('category.show');
Route::get('/search', Storefront\Listing::class)->name('search');
Route::get('/search/visual', Storefront\VisualSearch::class)->name('search.visual');
Route::get('/p/{product:slug}', Storefront\ProductDetail::class)->name('product.show');
Route::get('/s/{store:slug}', Storefront\StorePage::class)->name('store.show');
Route::get('/cart', Storefront\CartPage::class)->name('cart');
Route::get('/flash-sale', Storefront\FlashSale::class)->name('flash-sale');
Route::get('/group-buy/{team:code}', Storefront\GroupBuy\Team::class)->name('group-buy.team');
Route::get('/live', Storefront\Live\Index::class)->name('live.index');
Route::get('/live/{session:slug}', Storefront\Live\Room::class)->name('live.room');
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

// ===== iPay88 (docs/06 §D) =====
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/pay/{order:order_no}', [Ipay88Controller::class, 'pay'])->name('payments.ipay88.pay');
    // Built-in payment simulator confirm — only active when no merchant code is set (Ipay88Service::isMock).
    Route::post('/pay/{order:order_no}/mock/{result}', [Ipay88Controller::class, 'mockConfirm'])
        ->whereIn('result', ['success', 'fail'])
        ->name('payments.ipay88.mock');
    Route::get('/payments/processing/{order:order_no}', [Ipay88Controller::class, 'processing'])->name('payments.ipay88.processing');
    Route::get('/payments/status/{order:order_no}', function (Request $request, Order $order) {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json(['paid' => $order->payment_status === PaymentStatus::Paid]);
    })->name('payments.ipay88.status');
});

// Gateway callbacks — CSRF-exempt (bootstrap/app.php), signature-gated instead.
Route::post('/payments/ipay88/response', [Ipay88Controller::class, 'response'])->name('payments.ipay88.response');
Route::post('/payments/ipay88/backend', [Ipay88Controller::class, 'backend'])->name('payments.ipay88.backend');

// Courier tracking webhook (token-gated, CSRF-exempt in bootstrap/app.php).
Route::post('/shipping/easyparcel/tracking', [EasyParcelWebhookController::class, 'tracking'])->name('shipping.easyparcel.tracking');

// ===== Affiliate share links (M2.5) =====
Route::get('/r/{code}', [AffiliateReferralController::class, 'refer'])->name('affiliate.refer');

// ===== Help center =====
Route::get('/help', Storefront\Help\Index::class)->name('help.index');
Route::get('/help/article/{article}', Storefront\Help\Article::class)->name('help.article');
Route::get('/support', Storefront\Help\Tickets::class)->middleware('auth')->name('help.tickets');

// ===== Two-factor + social auth (M-FE wave 1) =====
Route::get('/two-factor-challenge', Storefront\Auth\TwoFactorChallenge::class)->name('two-factor.challenge');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('guest')->name('auth.google.callback');

// ===== Buyer account =====
Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Route::get('/dashboard', Storefront\Account\Dashboard::class)->name('dashboard');
    Route::get('/', Storefront\Account\Profile::class)->name('profile');
    Route::get('/messages', Storefront\Account\Messages::class)->name('messages');
    Route::get('/addresses', Storefront\Account\Addresses::class)->name('addresses');
    Route::get('/wishlist', Storefront\Account\WishlistPage::class)->name('wishlist');
    Route::get('/coins', Storefront\Account\Coins::class)->name('coins');
    Route::get('/affiliate', Storefront\Account\Affiliate::class)->name('affiliate');
    Route::get('/subscriptions', Storefront\Account\Subscriptions::class)->name('subscriptions');
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
