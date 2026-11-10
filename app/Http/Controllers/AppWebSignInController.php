<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * One-time web sign-in for the app (Haze 2026-11-10): the app's "Become a
 * seller" opens the web seller sign-up already signed in. The API issues a
 * 5-minute signed URL for the bearer-token user; this route consumes its
 * nonce once, logs that user into a web session and redirects.
 */
class AppWebSignInController extends Controller
{
    /** Destinations the app may ask for. */
    public const DESTINATIONS = ['seller-apply' => 'seller.apply'];

    private const TTL_MINUTES = 5;

    public static function issue(User $user, string $to): array
    {
        $nonce = Str::random(40);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        Cache::put(self::key($nonce), $user->id, $expiresAt);

        return [
            'url' => URL::temporarySignedRoute('app.web-sign-in', $expiresAt, ['user' => $user->id, 'nonce' => $nonce, 'to' => $to]),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $to = self::DESTINATIONS[(string) $request->query('to')] ?? null;
        // ponytail: pull() is get+forget, not atomic; two requests in the same
        // millisecond could both pass. Use Cache::lock if that ever matters.
        $userId = Cache::pull(self::key((string) $request->query('nonce')));

        abort_if($to === null || $userId === null || (int) $userId !== (int) $request->query('user'), 403);

        $user = User::query()->findOrFail($userId);
        abort_if($user->isSuspended(), 403);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route($to);
    }

    private static function key(string $nonce): string
    {
        return 'app-web-sign-in:'.$nonce;
    }
}
