<?php

namespace App\Support;

/**
 * Web URL → mobile app route (mobile plan §3), for the push payload that
 * rides on every database notification. Unknown pages map to null so the app
 * opens on its home tab rather than a screen it does not have.
 */
final class AppLinks
{
    /** @var array<string, string> regex on the URL path → app route (with $1 back-references) */
    private const MAP = [
        '#^/account/orders/(\d+)$#' => '/orders/$1',
        '#^/account/orders$#' => '/orders',
        '#^/seller/orders/(\d+)$#' => '/seller/orders/$1',
        '#^/account/messages$#' => '/account/messages',
        '#^/account/notifications$#' => '/account/notifications',
        '#^/account/coins$#' => '/account/coins',
        '#^/cart$#' => '/cart',
        '#^/p/([^/]+)$#' => '/p/$1',
        '#^/s/([^/]+)$#' => '/s/$1',
    ];

    public static function fromWebUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = rtrim($path, '/') ?: '/';

        foreach (self::MAP as $pattern => $route) {
            if (preg_match($pattern, $path)) {
                return preg_replace($pattern, $route, $path);
            }
        }

        return null;
    }
}
