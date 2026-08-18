<?php

namespace App\Services;

/**
 * The trust boundary for seller-supplied marketplace URLs.
 *
 * Sellers paste a URL; other people click it, in a new tab, under our branding.
 * Everything that decides whether a URL is safe lives HERE, so there is one
 * place to read and one place to test — callers only ever ask "does this
 * resolve?".
 *
 * The platform is DERIVED from the host rather than chosen by the seller: if the
 * form let them pick, they could label any link "Shopee".
 *
 * ⚠ Note for anyone extending this: the app's other allow-list,
 * Livewire\Storefront\Live\Room::embedUrl(), matches with `str_contains`, which
 * accepts `https://evil.com/#youtube.com/embed/`. Do not copy that. Host
 * comparison here is exact or dot-bounded, on the PARSED host only.
 */
class MarketplaceLinkResolver
{
    /** Same ceiling as the app's other URL field (Seller\LiveSessions). */
    public const MAX_LENGTH = 500;

    /**
     * Resolve a seller-supplied URL to a known platform.
     *
     * @return array{platform: string, label: string, url: string}|null
     *                                                                  null for anything not provably on an allow-listed host.
     */
    public function resolve(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        // Control characters (incl. newlines and NUL) never belong in a URL and
        // are how header/attribute splitting gets attempted. Refuse outright.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        // `https:/\/\shopee.com.my/x` parses with NO host at all — browsers read
        // those backslashes as slashes and PHP does not, so an absent host is a
        // rejection, never a pass-through.
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        // https only. This is what makes `javascript:`, `data:`, `vbscript:` and
        // the scheme-relative `//evil.com` unrepresentable rather than merely
        // discouraged. Case-folded because parse_url does NOT normalise the
        // scheme — measured: `HTTPS://…` comes back as the string 'HTTPS', and
        // without strtolower a seller pasting an uppercase URL is told their
        // perfectly good link is hostile.
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        // `https://shopee.com.my@evil.com/x` reads as Shopee to a human and
        // resolves to evil.com. The host check below already rejects it, but
        // PHP's parser and a browser's do not always agree on where the host
        // ends, so refuse the SHAPE instead of trusting them to match.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = rtrim(mb_strtolower($parts['host']), '.');

        foreach ((array) config('marketplaces.platforms', []) as $key => $platform) {
            foreach ((array) ($platform['hosts'] ?? []) as $allowed) {
                $allowed = mb_strtolower($allowed);

                // Exact, or a subdomain on a real dot boundary — `shopee.com.my`
                // must match `my.shopee.com.my` and must NOT match
                // `notshopee.com.my`.
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return [
                        'platform' => (string) $key,
                        'label' => (string) ($platform['label'] ?? $key),
                        'url' => $url,
                    ];
                }
            }
        }

        return null;
    }

    /** Brand names for the "we only support …" error, in config order. */
    public function supportedLabels(): string
    {
        return collect((array) config('marketplaces.platforms', []))
            ->map(fn (array $platform, string $key) => (string) ($platform['label'] ?? $key))
            ->join(', ');
    }
}
