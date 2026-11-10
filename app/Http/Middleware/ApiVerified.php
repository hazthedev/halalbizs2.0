<?php

namespace App\Http\Middleware;

use App\Http\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API twin of `verified`: a 403 envelope with code `email_unverified` instead
 * of a redirect to the notice page. Alias `verified.api`; checkout (slice 2)
 * sits behind it.
 */
class ApiVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return ApiResponse::error('email_unverified', __('Verify your email address to continue.'), 403);
        }

        return $next($request);
    }
}
