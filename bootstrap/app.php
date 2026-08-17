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

        // Reverse-proxy trust (AL-C4, then H-1b). This was `at: '*'` on the
        // assumption that a TLS-terminating proxy fronts the app and that
        // $request->secure() was therefore false in production. MEASURED on
        // the live host 2026-08-14 and the assumption was wrong on both counts:
        //
        //   REMOTE_ADDR  210.186.7.249   <- the real client (my own egress IP)
        //   SERVER_ADDR  103.191.76.66   <- the host itself
        //
        // There is no reverse proxy and no CDN. LiteSpeed terminates TLS and
        // hands the request to PHP in-process, so REMOTE_ADDR is already the
        // client and $_SERVER['HTTPS'] is already set — HSTS fires on a request
        // carrying no forwarded headers at all. Every X-Forwarded-* header that
        // arrives here is therefore attacker-supplied, and `at: '*'` trusted
        // all of them: X-Forwarded-For made $request->ip() a free-text field
        // the caller chooses, and X-Forwarded-Proto: http was enough to strip
        // the HSTS header off a response.
        //
        // Loopback rather than an empty list: nothing today connects from
        // 127.0.0.1, so this trusts nobody. But if a local reverse proxy is
        // ever added in front (Engintron and friends bind there), the failure
        // mode of NOT trusting it is that every visitor resolves to the proxy's
        // address and shares one rate-limit bucket — i.e. one attacker locks
        // out the whole site. Trusting loopback costs nothing now and forecloses
        // that. A public address must never go in this list.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // ...and because HEADER_X_FORWARDED_HOST is trusted above, the Host a
        // request claims is attacker-controlled unless it is checked HERE. It
        // was not, so `route('password.reset')` happily generated a link on
        // http://evil.example.com/ — a reset token delivered to the real user's
        // inbox pointing at someone else's server (audit H-1).
        //
        // No argument = the APP_URL host and its subdomains, which is exactly
        // what this app serves ({store}.<app host> is a real route). Verified
        // against the preview's own APP_URL (https://halalbizs2.0.weststar-dev.com,
        // read off the CLI-generated sitemap, so it is the configured value and
        // not a request echo). Laravel skips this in `local` and under tests, so
        // Herd's halalbizs2.0.test is unaffected.
        //
        // This closed one half of H-1; the other half — X-Forwarded-For making
        // $request->ip() attacker-chosen — was closed by narrowing trustProxies
        // above, once the host topology had actually been measured.
        $middleware->trustHosts();

        // Gateway callbacks are signature-gated, not CSRF-gated (docs/10:
        // never exempt anything else).
        $middleware->validateCsrfTokens(except: [
            'payments/ipay88/response',
            'payments/ipay88/backend',
            'shipping/easyparcel/tracking',
            'shipping/aftership/tracking',
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
