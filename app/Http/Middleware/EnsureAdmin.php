<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless($user->hasRole('admin'), 403);

        // 2FA is optional for admins (owner decision 2026-09-10): an admin who
        // has set it up still gets the login challenge; one who has not is let
        // straight through instead of being parked on the profile security tab.
        return $next($request);
    }
}
