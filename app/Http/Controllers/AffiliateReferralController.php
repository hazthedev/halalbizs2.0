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
        $target = $this->safeTarget($request->query('to')) ?? route('home');

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

    /**
     * Accept only a genuine in-app path (AL-C1). A naive `str_starts_with($to,
     * '/')` check passes a protocol-relative `//evil.com`, which browsers
     * resolve as an offsite `Location:` redirect, or a `/\evil.com`, which the
     * WHATWG URL spec treats a backslash exactly like a forward slash for —
     * both collapse to `//evil.com`. Browsers also silently strip ASCII
     * control characters (tab, CR, LF, …) before parsing a URL, so
     * `/\t/evil.com` looks safe to a plain string check but becomes
     * `//evil.com` once the browser drops the tab — strip them first so the
     * checks below see what the browser will actually resolve.
     */
    private function safeTarget(mixed $to): ?string
    {
        if (! is_string($to) || $to === '') {
            return null;
        }

        $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $to);

        if (! str_starts_with($stripped, '/') || str_starts_with($stripped, '//') || str_starts_with($stripped, '/\\')) {
            return null;
        }

        $parts = parse_url($stripped);

        if ($parts === false || ! empty($parts['scheme']) || ! empty($parts['host'])) {
            return null;
        }

        return $stripped;
    }
}
