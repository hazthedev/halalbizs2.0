<?php

namespace App\Http\Controllers;

use App\Enums\TwoFactorMethod;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\DeviceGuard;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One-time web sign-in for the app (Haze 2026-11-10): the app's "Become a
 * seller" opens the web seller sign-up already signed in. The API issues a
 * 5-minute signed URL for the bearer-token user; opening it shows
 * "Continue as <name>", and the confirm POST consumes its nonce once, logs
 * that user into a web session and redirects.
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

    /**
     * GET: show "Continue as <name>" only. Opening a link signs nobody in, so
     * a link someone forwards (login CSRF) or a chat preview fetch does
     * nothing until a person sees whose account it is and confirms.
     */
    public function show(Request $request): View
    {
        [$user] = $this->resolve($request, Cache::get(self::key((string) $request->query('nonce'))));

        return view('app-sign-in', ['user' => $user]);
    }

    /** POST (CSRF + same signed URL): consume the nonce once and sign in. */
    public function confirm(Request $request): RedirectResponse
    {
        // ponytail: pull() is get+forget, not atomic; two requests in the same
        // millisecond could both pass. Use Cache::lock if that ever matters.
        [$user, $to, $id] = $this->resolve($request, Cache::pull(self::key((string) $request->query('nonce'))));

        $destination = route($to, $id === null ? [] : [$id]);

        // A seller's app token is not proof of THIS browser (HB-NEWDEV-2FA):
        // an unseen device goes through the same code screen as the web login,
        // then lands on the asked-for page via url.intended.
        if (app(DeviceGuard::class)->needsCode($user, $request)) {
            $request->session()->regenerate();
            $request->session()->put([
                'two_factor:user_id' => $user->id,
                'two_factor:remember' => false,
                'two_factor:new_device' => ! $user->hasTwoFactor(),
                'url.intended' => $destination,
            ]);

            if (($user->two_factor_method ?? TwoFactorMethod::Email) === TwoFactorMethod::Email) {
                app(OtpService::class)->issue($user, OtpService::PURPOSE_2FA_EMAIL);
            }

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to($destination);
    }

    /** @return array{0: User, 1: string, 2: ?int} */
    private function resolve(Request $request, mixed $userId): array
    {
        $to = self::DESTINATIONS[(string) $request->query('to')] ?? null;

        abort_if($to === null || $userId === null || (int) $userId !== (int) $request->query('user'), 403);

        $user = User::query()->findOrFail($userId);
        abort_if($user->isSuspended() || ! self::allowed($user), 403);

        $id = in_array($request->query('to'), self::SUB_ORDER_DESTINATIONS, true) ? (int) $request->query('id') : null;
        abort_if($id !== null && ! self::ownsSubOrder($user, $id), 403);

        return [$user, $to, $id];
    }

    private static function key(string $nonce): string
    {
        return 'app-web-sign-in:'.$nonce;
    }
}
