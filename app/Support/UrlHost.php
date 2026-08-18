<?php

namespace App\Support;

/**
 * "Is this user-supplied URL on one of these hosts?" — the one place that
 * question is answered.
 *
 * It exists because the app got this wrong once already: Live\Room::embedUrl()
 * allow-listed with `str_contains($url, 'youtube.com/embed/')`, which
 * `https://evil.com/#youtube.com/embed/` satisfies, and that string went
 * straight into an iframe src. A substring test asks about the whole URL when
 * the only thing that decides where a browser goes is the HOST.
 *
 * Every rule below is here rather than at a call site so that a third
 * allow-list cannot reintroduce the same bug in a slightly different shape.
 */
class UrlHost
{
    /**
     * The parsed, normalised host, or null if the URL is not one we will ever
     * hand to a browser.
     *
     * Rejects, in order: control characters, an unparseable URL, any scheme
     * other than https, and any URL carrying credentials.
     */
    public static function safeHost(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Browsers strip ASCII control characters before resolving a URL and PHP
        // does not, so `https://good.example/p\nX: 1` parses to a clean host here
        // while meaning something else downstream. Measured, not assumed.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        // `https:/\/\evil.example/x` parses with NO host — browsers read those
        // backslashes as slashes and PHP does not.
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        // parse_url does NOT normalise the scheme: `HTTPS://…` comes back as the
        // literal 'HTTPS', so this has to be case-folded or a legitimate pasted
        // URL reads as hostile.
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        // `https://good.example@evil.example/x` reads as good.example to a human
        // and resolves to evil.example. The host comparison below already rejects
        // it, but PHP's parser and a browser's do not always agree on where the
        // host ends, so refuse the shape rather than trust them to match.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return rtrim(mb_strtolower($parts['host']), '.');
    }

    /**
     * Whether the URL's host is one of $hosts, matched exactly or on a real dot
     * boundary — `shopee.com.my` matches `my.shopee.com.my` and never
     * `notshopee.com.my`.
     *
     * @param  array<int, string>  $hosts
     */
    public static function isOn(?string $url, array $hosts): bool
    {
        $host = self::safeHost($url);

        if ($host === null) {
            return false;
        }

        foreach ($hosts as $allowed) {
            $allowed = mb_strtolower(trim((string) $allowed));

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }
}
