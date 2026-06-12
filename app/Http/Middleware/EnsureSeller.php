<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seller routes require the seller role AND an approved store.
 * Pending/rejected applicants are routed to their status screen.
 */
class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $store = $user->store;

        if ($store === null) {
            return redirect()->route('seller.apply');
        }

        if (! $user->hasRole('seller') || ! $store->isApproved()) {
            return redirect()->route('seller.status');
        }

        return $next($request);
    }
}
