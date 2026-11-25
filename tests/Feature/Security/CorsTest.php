<?php

/*
 * HB-G180 (HB-DEC-1124f item 3): the API answers cross-origin browser calls
 * from https://halalbizs.com only. The phone app sends no Origin, so CORS
 * never applies to it; the website itself is same-origin.
 */

$preflight = fn (string $origin) => test()->call('OPTIONS', '/api/v1/categories', server: [
    'HTTP_ORIGIN' => $origin,
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
]);

test('HB-G180: a preflight from the live site is allowed', function () use ($preflight) {
    expect($preflight('https://halalbizs.com')->headers->get('Access-Control-Allow-Origin'))->toBe('https://halalbizs.com');
});

test('HB-G180: a preflight from any other origin is not allowed', function () use ($preflight) {
    // With one allowed origin the header is that fixed origin, never '*' and
    // never an echo of the caller, so the browser refuses the other origin.
    expect($preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('*')
        ->not->toBe('https://evil.example');
});

test('HB-G180: allowed origins come from CORS_ALLOWED_ORIGINS, default the live site', function () {
    expect(config('cors.allowed_origins'))->toBe(['https://halalbizs.com']);
});
