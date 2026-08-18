<?php

use App\Support\UrlHost;

// One allow-list helper now backs both the marketplace links a shopper clicks
// and the video URL that lands in an iframe src, so these cases are the floor
// under both features.

dataset('hostile urls', [
    'plain http' => 'http://youtube.com/embed/abc',
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'scheme-relative' => '//youtube.com/embed/abc',
    'no scheme at all' => 'youtube.com/embed/abc',
    // The bug this helper was extracted to kill: allow-listed string present,
    // host is somebody else's.
    'allow-listed host in the fragment' => 'https://evil.example/x#youtube.com/embed/abc',
    'allow-listed host in the query' => 'https://evil.example/?u=youtube.com/embed/abc',
    'allow-listed host in the path' => 'https://evil.example/youtube.com/embed/abc',
    // Reads as YouTube, resolves to evil.example.
    'userinfo spoof' => 'https://youtube.com@evil.example/embed/abc',
    'userinfo with password' => 'https://youtube.com:x@evil.example/embed/abc',
    // Suffix match without a dot boundary.
    'lookalike suffix' => 'https://notyoutube.com/embed/abc',
    'lookalike prefix' => 'https://youtube.com.evil.example/embed/abc',
    // Backslashes: browsers read them as slashes, PHP does not, and the host
    // comes back empty.
    'backslash host' => 'https:/\/\youtube.com/embed/abc',
    'control character' => "https://youtube.com/embed/abc\nX-Evil: 1",
    'empty' => '',
    'null' => null,
]);

it('refuses any url not provably on an allow-listed host', function (?string $url) {
    expect(UrlHost::isOn($url, ['youtube.com', 'facebook.com']))->toBeFalse();
})->with('hostile urls');

dataset('allowed urls', [
    'exact host' => 'https://youtube.com/embed/abc',
    'www subdomain' => 'https://www.youtube.com/embed/abc',
    'mobile subdomain' => 'https://m.youtube.com/embed/abc',
    'trailing dot' => 'https://youtube.com./embed/abc',
    'uppercase host' => 'https://WWW.YOUTUBE.COM/embed/abc',
    // parse_url returns the scheme verbatim, so this only passes because
    // safeHost() case-folds it.
    'uppercase scheme' => 'HTTPS://youtube.com/embed/abc',
    'second host in the list' => 'https://facebook.com/plugins/video.php?href=x',
]);

it('accepts a url whose parsed host is on the list', function (string $url) {
    expect(UrlHost::isOn($url, ['youtube.com', 'facebook.com']))->toBeTrue();
})->with('allowed urls');

it('matches on a dot boundary, so a lookalike domain is not a subdomain', function () {
    expect(UrlHost::isOn('https://my.shopee.com.my/p/1', ['shopee.com.my']))->toBeTrue()
        ->and(UrlHost::isOn('https://notshopee.com.my/p/1', ['shopee.com.my']))->toBeFalse();
});

it('returns the normalised host so callers do not re-parse', function () {
    expect(UrlHost::safeHost('HTTPS://WWW.Shopee.Com.My./p/1'))->toBe('www.shopee.com.my')
        ->and(UrlHost::safeHost('http://shopee.com.my/p/1'))->toBeNull();
});

it('treats an empty allow-list as allowing nothing', function () {
    expect(UrlHost::isOn('https://youtube.com/embed/abc', []))->toBeFalse()
        ->and(UrlHost::isOn('https://youtube.com/embed/abc', ['', '  ']))->toBeFalse();
});
