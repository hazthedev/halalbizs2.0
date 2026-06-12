<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDisplayCurrency
{
    public function __construct(private GeneralSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $currency = session('display_currency')
            ?? $request->user()?->preferred_currency
            ?? $this->settings->base_currency;

        if (! in_array($currency, $this->settings->display_currencies, true)) {
            $currency = $this->settings->base_currency;
        }

        session(['display_currency' => $currency]);

        return $next($request);
    }
}
