<?php

namespace App\Support;

use App\Enums\AuthContext;
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
    /**
     * Resolve the post-auth destination, consuming `url.intended`.
     *
     * $context is the door the user came through (a /seller/login vs /admin/login
     * vs plain /login). It only influences the fallback when no legitimate
     * intended URL survived — and only when the user actually holds that hat, so
     * it can never grant a landing a role check wouldn't.
     */
    public static function url(User $user, ?AuthContext $context = null): string
    {
        $intended = session()->pull('url.intended');

        if ($intended !== null && self::mayVisit($user, $intended)) {
            return $intended;
        }

        if ($context !== null && ($contextHome = self::contextHome($user, $context)) !== null) {
            return $contextHome;
        }

        return self::home($user);
    }

    /** The context's preferred landing, but only if this user may enter it. */
    private static function contextHome(User $user, AuthContext $context): ?string
    {
        $route = $context->homeRoute();

        if ($route === null) {
            return null;
        }

        // Reuse the same gate as an intended URL into that section — a buyer who
        // opened /seller/login still can't be dropped into the Seller Centre.
        return self::mayVisit($user, '/'.$context->value)
            ? route($route)
            : null;
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
        if (! self::isLocalUrl($url)) {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        // Auth entrances (the router's `guest` group + 2FA challenge) are never a
        // landing: a stale one would bounce a fresh login straight back to it.
        if (in_array($path, ['login', 'register', 'forgot-password', 'two-factor-challenge', 'seller/login', 'seller/register', 'admin/login'], true)
            || Str::startsWith($path, 'reset-password/')) {
            return false;
        }

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

    private static function isLocalUrl(string $url): bool
    {
        for ($depth = 0; $depth < 5; $depth++) {
            if (str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) {
                return false;
            }

            $parts = parse_url($url);
            if ($parts === false) {
                return false;
            }

            if (isset($parts['scheme']) || isset($parts['host'])) {
                $app = parse_url(config('app.url'));

                return isset($parts['scheme'], $parts['host'], $app['scheme'], $app['host'])
                    && strcasecmp($parts['scheme'], $app['scheme']) === 0
                    && strcasecmp($parts['host'], $app['host']) === 0
                    && ($parts['port'] ?? null) === ($app['port'] ?? null)
                    && ! isset($parts['user'])
                    && ! isset($parts['pass']);
            }

            if (str_starts_with($url, '//')) {
                return false;
            }

            $decoded = rawurldecode($url);
            if ($decoded === $url) {
                return true;
            }

            $url = $decoded;
        }

        return false;
    }

    /** The natural landing for this user's most privileged hat. */
    private static function home(User $user): string
    {
        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        return route('home');
    }
}
