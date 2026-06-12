<?php

namespace App\Http\Controllers;

use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function locale(Request $request, GeneralSettings $settings): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $settings->enabled_locales)],
        ])['locale'];

        session(['locale' => $locale]);
        $request->user()?->update(['preferred_locale' => $locale]);

        return back();
    }

    public function currency(Request $request, GeneralSettings $settings): RedirectResponse
    {
        $currency = $request->validate([
            'currency' => ['required', 'string', 'in:'.implode(',', $settings->display_currencies)],
        ])['currency'];

        session(['display_currency' => $currency]);
        $request->user()?->update(['preferred_currency' => $currency]);

        return back();
    }
}
