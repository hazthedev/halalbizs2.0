<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header-driven locale + display currency for the stateless API (mobile plan
 * §1). Accept-Language's first tag picks the locale; X-Display-Currency picks
 * the display currency. Both fall back to the admin defaults when the value is
 * not enabled.
 *
 * The currency is written to the session store on purpose even though the api
 * group has no StartSession: nothing persists it, so it is request-scoped
 * memory — exactly what CurrencyConverter::display() and CheckoutService read
 * via session('display_currency'), so they keep working unchanged.
 */
class ApiLocale
{
    public function __construct(private GeneralSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = Str::of((string) $request->header('Accept-Language', ''))
            ->before(',')->before(';')->before('-')->trim()->lower()->toString();

        if (! in_array($locale, $this->settings->enabled_locales, true)) {
            $locale = $this->settings->default_locale;
        }

        app()->setLocale($locale);

        $currency = strtoupper(trim((string) $request->header('X-Display-Currency', '')));

        if (! in_array($currency, $this->settings->display_currencies, true)) {
            $currency = $this->settings->base_currency;
        }

        session()->put('display_currency', $currency);

        return $next($request);
    }
}
