<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Observers\AdminAlertObserver;
use App\Observers\SlugRedirectObserver;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Local/dev SMS stub — swap for a real gateway driver at cutover.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    public function boot(): void
    {
        // Slug changes leave a 301 behind (docs/09 §F).
        Product::observe(SlugRedirectObserver::class);
        Store::observe(SlugRedirectObserver::class);
        Category::observe(SlugRedirectObserver::class);

        // Admin bell alerts (database only): pending stores, payout
        // requests, escalated/disputed returns, iPay88 signature mismatches.
        Store::observe(AdminAlertObserver::class);
        Payout::observe(AdminAlertObserver::class);
        ReturnRequest::observe(AdminAlertObserver::class);
        Payment::observe(AdminAlertObserver::class);

        // Plain MYR amount: @money($sen)
        Blade::directive('money', function (string $expression) {
            return "<?php echo \App\Support\Money::format($expression); ?>";
        });

        // Display-currency amount (≈ converted when non-MYR): @price($sen)
        Blade::directive('price', function (string $expression) {
            return "<?php echo app(\App\Services\CurrencyConverter::class)->display($expression); ?>";
        });
    }
}
