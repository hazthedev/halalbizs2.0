<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale resolution: session → user preference → default (docs/04 §9.10).
 */
class SetLocale
{
    public function __construct(private GeneralSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale')
            ?? $request->user()?->preferred_locale
            ?? $this->settings->default_locale;

        if (! in_array($locale, $this->settings->enabled_locales, true)) {
            $locale = $this->settings->default_locale;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
