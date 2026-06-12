<?php

namespace App\Support;

/**
 * Subdomains that can never become store fronts.
 */
class ReservedSubdomains
{
    public const ALL = [
        'www', 'admin', 'seller', 'api', 'app', 'pay', 'payments', 'mail',
        'help', 'support', 'static', 'cdn', 'assets', 'media', 'blog',
        'shop', 'store', 'my', 'account', 'checkout', 'cart', 'search',
        'login', 'register', 'staging', 'dev', 'test', 'status',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::ALL, true);
    }
}
