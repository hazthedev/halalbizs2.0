<?php

namespace App\Services;

use App\Support\UrlHost;

/**
 * The trust boundary for seller-supplied outbound URLs.
 *
 * Sellers paste a URL; other people click it, in a new tab, under our branding.
 * Everything that decides whether a URL is safe lives HERE, so there is one
 * place to read and one place to test.
 *
 * It answers TWO questions, and they are not the same question:
 *
 *   isSafe()  — may we ever put this in an href? https only, no credentials, no
 *               control characters, within the column's length. Since 2026-08-20
 *               a seller may link anywhere (Haze's call), so this is the gate a
 *               link must pass to be stored at all.
 *   resolve() — is this host one of the marketplaces we allow-list? A yes is
 *               what "verified" means; a no is still storable, just not vouched
 *               for.
 *
 * The platform is DERIVED from the host rather than chosen by the seller: if the
 * form let them pick, they could label any link "Shopee".
 *
 * Host matching itself is UrlHost's job — the video-embed allow-list in
 * Live\Room uses the same helper, so neither can drift into accepting a host
 * the other rejects.
 */
class MarketplaceLinkResolver
{
    /** Same ceiling as the app's other URL field (Seller\LiveSessions). */
    public const MAX_LENGTH = 500;

    /**
     * May this URL be handed to a browser at all?
     *
     * Deliberately NOT "is it a marketplace" — an unrecognised host is allowed,
     * a hostile SHAPE is not. Every rule behind this lives in UrlHost, which the
     * live-embed allow-list shares, so neither can drift into accepting
     * something the other rejects.
     */
    public function isSafe(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== ''
            && mb_strlen($url) <= self::MAX_LENGTH
            && UrlHost::safeHost($url) !== null;
    }

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

        // Scheme, userinfo, control characters and dot-bounded host matching all
        // live in UrlHost — the app has two allow-lists now and they must not
        // drift apart. See that class for why each rule is there.
        foreach ((array) config('marketplaces.platforms', []) as $key => $platform) {
            if (UrlHost::isOn($url, (array) ($platform['hosts'] ?? []))) {
                return [
                    'platform' => (string) $key,
                    'label' => (string) ($platform['label'] ?? $key),
                    'url' => $url,
                ];
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
