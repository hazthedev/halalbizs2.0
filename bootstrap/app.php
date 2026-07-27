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

            Route::middleware(['web', 'auth', EnsureAdmin::class])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SetDisplayCurrency::class,
            SecurityHeaders::class,
        ]);

        // 301s old slugs — queries only on 404s (docs/09 §F). PREPENDED so it
        // sits outside SubstituteBindings: binding misses render their 404
        // before reaching appended (inner) middleware, and only outer layers
        // see that response come back through.
        $middleware->web(prepend: [
            HandleUrlRedirects::class,
        ]);

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
