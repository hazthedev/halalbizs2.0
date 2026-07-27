<?php

namespace App\Http\Middleware;

use App\Settings\TrackingSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/10 hardening. `unsafe-inline`/`unsafe-eval` stay in script-src
 * because Alpine evaluates expressions at runtime and several views ship
 * inline scripts — foreign origins are still blocked.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $scriptHosts = ['https://challenges.cloudflare.com'];
            $frameHosts = ['https://challenges.cloudflare.com'];

            $tracking = app(TrackingSettings::class);

            if ($tracking->ga4_id !== '') {
                $scriptHosts[] = 'https://www.googletagmanager.com';
            }

            if ($tracking->meta_pixel_id !== '') {
                $scriptHosts[] = 'https://connect.facebook.net';
            }

            $scripts = implode(' ', $scriptHosts);
            $frames = implode(' ', $frameHosts);

            // The iPay88 bridge (resources/views/payments/bridge.blade.php)
            // auto-submits a POST form to the gateway's hosted-page entry URL
            // (Ipay88Service::entryUrl) — sandbox and production hosts both
            // need to stay in form-action or the payment bridge breaks.
            $formActions = "'self' https://sandbox.ipay88.com.my https://payment.ipay88.com.my";

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' {$scripts}; "
                ."style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; "
                ."media-src 'self' blob:; font-src 'self' data:; connect-src 'self'; "
                ."frame-src {$frames}; frame-ancestors 'self'; form-action {$formActions}; "
                ."object-src 'none'; base-uri 'self'"
            );
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS only over HTTPS (docs/10) — never on local HTTP, so dev/tests
        // and plain-HTTP health checks are unaffected.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
