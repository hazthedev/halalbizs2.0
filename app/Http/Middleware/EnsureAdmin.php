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
            return redirect()->guest(route('login'));
        }

        abort_unless($user->hasRole('admin'), 403);

        // Admin accounts must carry 2FA — park them on the profile security
        // section until it's set up.
        if (! $user->hasTwoFactor()) {
            return redirect()
                ->to(route('account.profile').'#security')
                ->with('toast', [
                    'message' => __('Set up two-factor authentication to access the admin panel.'),
                    'type' => 'error',
                ]);
        }

        return $next($request);
    }
}
