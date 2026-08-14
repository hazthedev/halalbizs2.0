<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// H-1b. Measured on the live host 2026-08-14: REMOTE_ADDR is the real client
// (LiteSpeed hands the request to PHP in-process, no reverse proxy, no CDN),
// so every X-Forwarded-* header that arrives is attacker-supplied. Under the
// previous `at: '*'` these two cases were indistinguishable — the header won
// both times, which made $request->ip() a free-text field the caller chooses
// and left every IP-keyed limiter decorative.
//
// Note the test environment's own REMOTE_ADDR is 127.0.0.1, i.e. exactly the
// address the config trusts. Both cases therefore have to set REMOTE_ADDR
// explicitly; a test that leans on the default would only ever exercise the
// second one and would pass just as happily against `at: '*'`.
beforeEach(function () {
    Route::middleware('web')->get('/__ip-probe', fn (Request $request) => $request->ip().' '.$request->getScheme());
});

test('a forwarded header from a public client is ignored', function () {
    $this->call('GET', '/__ip-probe', server: [
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
    ])->assertOk()->assertSee('198.51.100.7');
});

test('a forwarded header from loopback is honoured, so a local proxy still works', function () {
    $this->call('GET', '/__ip-probe', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
    ])->assertOk()->assertSee('203.0.113.99');
});

// The other half of the same setting, and the one nobody had recorded: with
// '*' trusted, `X-Forwarded-Proto: http` was enough to strip HSTS off a
// response over real TLS (confirmed against the live preview before the fix).
test('a spoofed X-Forwarded-Proto cannot downgrade the request', function () {
    $this->call('GET', 'https://localhost/__ip-probe', server: [
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_X_FORWARDED_PROTO' => 'http',
    ])->assertOk()->assertSee('198.51.100.7 https');
});
