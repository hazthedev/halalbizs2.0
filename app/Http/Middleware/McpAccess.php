<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Guard;
use Symfony\Component\HttpFoundation\Response;

class McpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // This app configures Sanctum for bearer tokens only (no web guard).
        // Do not update personal_access_tokens.last_used_at on a read-only call.
        $user = $request->bearerToken()
            ? (new Guard(app('auth'), config('sanctum.expiration'), 'users', trackLastUsedAt: false))($request)
            : null;

        $request->attributes->set('mcp.admin', $user instanceof User && ! $user->isSuspended() && $user->hasRole('admin'));

        return $next($request);
    }
}
