<?php

namespace App\Support;

/**
 * A seller's pasted live-stream URL -> an iframe src WE build, or null.
 *
 * Recognised watch URLs are never passed through: the video id is extracted and
 * a known-good embed URL is built, so the rest of the seller's string is
 * discarded. Every path is gated on the parsed HOST via UrlHost (a substring
 * test lets `https://evil.com/#youtube.com/live/x` through).
 */
class LiveVideoUrl
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'youtu.be'];

    private const FACEBOOK_HOSTS = ['facebook.com', 'fb.watch'];

    /** Hosts whose own embed URLs (seller pasted the embed code's src) we keep verbatim. */
    private const EMBED_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'facebook.com'];

    /** Platforms that refuse embedding: shown as a "Watch on X" link, never an iframe. */
    private const EXTERNAL_HOSTS = [
        'shopee' => ['shopee.com.my', 'shopee.sg', 'shp.ee'],
        'tiktok' => ['tiktok.com'],
        'lazada' => ['lazada.com.my'],
    ];

    /**
     * Everything the room and the seller form need in one read.
     *
     * @return array{platform: string, embed: ?string, link: ?string}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if (($embed = self::embed($url)) !== null) {
            $platform = UrlHost::isOn($url, ['facebook.com', 'fb.watch']) ? 'facebook' : 'youtube';

            return ['platform' => $platform, 'embed' => $embed, 'link' => null];
        }

        foreach (self::EXTERNAL_HOSTS as $platform => $hosts) {
            if (UrlHost::isOn($url, $hosts)) {
                return ['platform' => $platform, 'embed' => null, 'link' => $url];
            }
        }

        return null;
    }

    public static function embed(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (($id = self::youtubeId($url)) !== null) {
            return 'https://www.youtube.com/embed/'.$id;
        }

        if (self::isFacebookVideo($url)) {
            return 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false';
        }

        // Pasted embed src: passed through, so the host must be provably ours.
        if (UrlHost::isOn($url, self::EMBED_HOSTS)
            && (str_contains($url, '/embed/') || str_contains($url, '/plugins/'))) {
            return $url;
        }

        return null;
    }

    /** Public thumbnail for the live list; YouTube only (we know its id), else null. */
    public static function thumbnail(?string $url): ?string
    {
        $id = self::youtubeId(trim((string) $url));

        return $id ? "https://img.youtube.com/vi/{$id}/mqdefault.jpg" : null;
    }

    private static function youtubeId(string $url): ?string
    {
        if (! UrlHost::isOn($url, self::YOUTUBE_HOSTS)) {
            return null;
        }

        $host = UrlHost::safeHost($url);
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === 'youtu.be') {
            return preg_match('~^/([\w-]{11})(?:/|$)~', $path, $m) ? $m[1] : null;
        }

        if (preg_match('~^/(?:live|shorts|embed)/([\w-]{11})(?:/|$)~', $path, $m)) {
            return $m[1];
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if ($path === '/watch' && is_string($query['v'] ?? null) && preg_match('~^[\w-]{11}$~', $query['v'])) {
            return $query['v'];
        }

        return null;
    }

    private static function isFacebookVideo(string $url): bool
    {
        if (! UrlHost::isOn($url, self::FACEBOOK_HOSTS)) {
            return false;
        }

        $host = UrlHost::safeHost($url);
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === 'fb.watch' || str_ends_with($host, '.fb.watch')) {
            return preg_match('~^/[\w-]+~', $path) === 1;
        }

        // www / m / bare only; other subdomains (business., developers.) are not video pages.
        if (! in_array($host, ['facebook.com', 'www.facebook.com', 'm.facebook.com'], true)) {
            return false;
        }

        if (preg_match('~/videos/|^/reel/\w+|^/watch/?$~', $path) !== 1) {
            return false;
        }

        if ($path === '/watch' || $path === '/watch/') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return is_string($query['v'] ?? null) && ctype_digit($query['v']);
        }

        return true;
    }
}
