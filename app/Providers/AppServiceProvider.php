<?php

namespace App\Providers;

use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
