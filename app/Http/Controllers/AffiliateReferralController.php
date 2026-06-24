<?php

namespace App\Http\Controllers;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Affiliate share link (M2.5): /r/{code} records a click, drops the last-click
 * attribution cookie and forwards the shopper to the target page. Defensive —
 * an unknown/suspended code simply lands the visitor on the homepage.
 */
class AffiliateReferralController extends Controller
{
    public function refer(Request $request, string $code): RedirectResponse
    {
        // Only forward to safe in-app paths (no open redirects).
        $to = $request->query('to');
        $target = is_string($to) && str_starts_with($to, '/') ? $to : route('home');

        if (! config('affiliate.enabled', true)) {
            return redirect($target);
        }

        $affiliate = Affiliate::where('code', $code)->where('status', AffiliateStatus::Active)->first();

        if ($affiliate === null) {
            return redirect($target);
        }

        $affiliate->increment('clicks');

        $minutes = (int) config('affiliate.cookie_days', 30) * 24 * 60;

        return redirect($target)->cookie((string) config('affiliate.cookie', 'aff_ref'), $affiliate->code, $minutes);
    }
}
