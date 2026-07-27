<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Where to land a user after login / 2FA.
 *
 * Laravel's redirect()->guest() stashes whatever URL a guest bounced off as
 * `url.intended` — including sections this user can never enter. The worst
 * case was real: a guest touches /seller, logs in as the ADMIN, and the stale
 * intended URL walks them into EnsureSeller's no-store branch — the platform
 * admin staring at the "Become a seller" application.
 *
 * So the intended URL is honoured only when this user could actually go
 * there; otherwise they land at their natural home (admin panel → seller
 * centre → storefront).
 */
class PostLoginRedirect
{
    /** Resolve the post-auth destination, consuming `url.intended`. */
    public static function url(User $user): string
    {
        $intended = session()->pull('url.intended');

        if ($intended !== null && self::mayVisit($user, $intended)) {
            return $intended;
        }

        return self::home($user);
    }

    /**
     * May this user enter the section the URL points into?
     *
     * Mirrors the section gates (EnsureAdmin / EnsureSeller) by prefix rather
     * than invoking them: /seller/apply and /seller/status are deliberately
     * auth-only (a buyer who clicked "Become a seller" must come back to it),
     * the rest of /seller/* needs an approved seller, /admin/* needs the role.
     */
    private static function mayVisit(User $user, string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === 'seller/apply' || $path === 'seller/status') {
            return true;
        }

        if ($path === 'seller' || Str::startsWith($path, 'seller/')) {
            return $user->hasRole('seller') && $user->store !== null && $user->store->isApproved();
        }

        if ($path === 'admin' || Str::startsWith($path, 'admin/')) {
            return $user->hasRole('admin');
        }

        return true;
    }

    /** The natural landing for this user's most privileged hat. */
    private static function home(User $user): string
    {
        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('seller') && $user->store !== null && $user->store->isApproved()) {
            return route('seller.dashboard');
        }

        return route('home');
    }
}
