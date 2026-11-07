<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended account's bearer tokens stop working at once (alias
 * `suspended.api`, on every auth:sanctum group). Admin suspension also
 * deletes the tokens; this is the backstop for a token issued in between.
 */
class ApiNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            $user->currentAccessToken()?->delete();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
