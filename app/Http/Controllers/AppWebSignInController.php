<?php

namespace App\Http\Controllers;

use App\Models\SubOrder;
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
    /** Destinations the app may ask for: fixed named routes, never a URL. */
    public const DESTINATIONS = [
        'seller-apply' => 'seller.apply',
        'seller-certificates' => 'seller.certificates',
        'seller-vouchers' => 'seller.vouchers.index',
        'support' => 'help.tickets',
        'invoice' => 'account.orders.invoice',
    ];

    /** Destinations whose route takes a sub-order id: the buyer must own it (as InvoiceController::buyer). */
    public const SUB_ORDER_DESTINATIONS = ['invoice'];

    private const TTL_MINUTES = 5;

    /**
     * Admin accounts never sign into the web through the app (Haze
     * 2026-11-10): an admin web session must come through the web login.
     */
    public static function allowed(User $user): bool
    {
        return ! $user->is_superadmin && ! $user->hasRole('admin');
    }

    public static function ownsSubOrder(User $user, int $subOrderId): bool
    {
        return SubOrder::query()->whereKey($subOrderId)->whereHas('order', fn ($q) => $q->where('user_id', $user->id))->exists();
    }

    public static function issue(User $user, string $to, ?int $id = null): array
    {
        $nonce = Str::random(40);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        Cache::put(self::key($nonce), $user->id, $expiresAt);

        return [
            'url' => URL::temporarySignedRoute('app.web-sign-in', $expiresAt, ['user' => $user->id, 'nonce' => $nonce, 'to' => $to, 'id' => $id]),
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
        abort_if($user->isSuspended() || ! self::allowed($user), 403);

        $id = in_array($request->query('to'), self::SUB_ORDER_DESTINATIONS, true) ? (int) $request->query('id') : null;
        abort_if($id !== null && ! self::ownsSubOrder($user, $id), 403);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route($to, $id === null ? [] : [$id]);
    }

    private static function key(string $nonce): string
    {
        return 'app-web-sign-in:'.$nonce;
    }
}
