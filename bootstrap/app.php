<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSeller;
use App\Http\Middleware\HandleUrlRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetDisplayCurrency;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'verified', EnsureSeller::class])
                ->prefix('seller')
                ->name('seller.')
                ->group(base_path('routes/seller.php'));

            // 'verified' mirrors the seller group (AL-C5): EnsureAdmin checks
            // role + 2FA but not email ownership, and email-method 2FA would
            // otherwise deliver codes to an unproven address. Staff invites
            // markEmailAsVerified() on create; pre-existing admin rows are
            // backfilled by the 2026_07_27_000010 migration.
            Route::middleware(['web', 'auth', 'verified', EnsureAdmin::class])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SetDisplayCurrency::class,
        ]);

        // SecurityHeaders is registered on the application's GLOBAL
        // middleware stack (not the 'web' group) so it wraps the WHOLE HTTP
        // kernel pipeline, including $router->dispatch() itself — that's the
        // only way to also catch a completely UNMATCHED URI, whose
        // NotFoundHttpException is thrown by route-matching before any 'web'
        // group middleware (even one prepended to that group) ever runs.
        // Laravel renders an exception at the pipe wrapping the throw site
        // and returns outward, so only middleware OUTER than the throw sees
        // the response — appended (inner) middleware never does. Without
        // this, 404s (unmatched URIs, and route-model binding misses thrown
        // in SubstituteBindings), 419s (thrown in ValidateCsrfToken) all
        // shipped with no CSP, X-Frame-Options, nosniff or HSTS (AL-C2).
        $middleware->prepend(SecurityHeaders::class);

        // 301s old slugs — queries only on 404s (docs/09 §F). PREPENDED so it
        // sits outside SubstituteBindings: binding misses render their 404
        // before reaching appended (inner) middleware, and only outer layers
        // see that response come back through.
        $middleware->web(prepend: [
            HandleUrlRedirects::class,
        ]);

        // Reverse-proxy trust (AL-C4): AppServiceProvider forces the https
        // scheme for URL generation on the assumption that a TLS-terminating
        // proxy (cPanel/LiteSpeed) sits in front of this app, but nothing
        // told Laravel to trust the X-Forwarded-* headers that carry that
        // fact — so $request->secure() was false in prod (the HSTS branch in
        // SecurityHeaders never fired) and the `api` rate limiter keyed on
        // $request->ip() saw the proxy's address instead of the real client.
        //
        // ⚠ NEEDS VERIFICATION ON THE PRODUCTION HOST — the exact
        // cPanel/LiteSpeed topology (whether the app port is ever reachable
        // directly, bypassing the proxy/CDN) is unknown from here. `at: '*'`
        // trusts whichever host makes the TCP connection to PHP-FPM, which is
        // correct ONLY if that connection always originates from the
        // LiteSpeed/CDN edge. NARROW THIS to the real proxy/CDN CIDR
        // block(s) once that topology is confirmed — trusting '*' when the
        // app is directly reachable lets any caller spoof their IP via
        // X-Forwarded-For.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Gateway callbacks are signature-gated, not CSRF-gated (docs/10:
        // never exempt anything else).
        $middleware->validateCsrfTokens(except: [
            'payments/ipay88/response',
            'payments/ipay88/backend',
            'shipping/easyparcel/tracking',
        ]);

        $middleware->alias([
            'seller' => EnsureSeller::class,
            'admin' => EnsureAdmin::class,
        ]);

        // The `auth` middleware bounces guests before a section guard runs, so
        // send them to the branded door that matches the section they reached
        // for. Keeps the /seller/login and /admin/login entrances consistent
        // whether a guest arrives via a deep link or a section guard.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            if ($request->is('seller', 'seller/*')) {
                return route('seller.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
