<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSeller;
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
        ]);

        // Gateway callbacks are signature-gated, not CSRF-gated (docs/10:
        // never exempt anything else).
        $middleware->validateCsrfTokens(except: [
            'payments/ipay88/response',
            'payments/ipay88/backend',
        ]);

        $middleware->alias([
            'seller' => EnsureSeller::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
