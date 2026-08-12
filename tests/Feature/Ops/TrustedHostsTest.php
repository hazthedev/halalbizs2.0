<?php

use Illuminate\Http\Middleware\TrustHosts;

// H-1 (host half): X_FORWARDED_HOST is a trusted header, so without a trusted-host
// allowlist the Host is attacker-controlled and route('password.reset') generates
// the reset link on the attacker's domain. Laravel disables TrustHosts under tests
// (shouldSpecifyTrustedHosts()), so this asserts the PATTERN the app configures
// rather than driving it through the kernel.
test('the trusted-host middleware is actually registered', function () {
    // Without this, the assertions below still pass: hosts() falls back to the
    // APP_URL pattern whether or not the middleware is in the stack, so checking
    // the pattern alone proves nothing about the app.
    expect(app(Illuminate\Contracts\Http\Kernel::class)->getGlobalMiddleware())
        ->toContain(TrustHosts::class);
});

test('only the app host and its subdomains are trusted', function () {
    config(['app.url' => 'https://halalbizs2.0.weststar-dev.com']);

    $patterns = array_filter(app(TrustHosts::class)->hosts());

    expect($patterns)->not->toBeEmpty();

    $matches = fn (string $host): bool => collect($patterns)
        ->contains(fn (string $p) => (bool) preg_match('{'.$p.'}i', $host));

    // The app itself, and the store subdomains it really serves.
    expect($matches('halalbizs2.0.weststar-dev.com'))->toBeTrue();
    expect($matches('padi-emas.halalbizs2.0.weststar-dev.com'))->toBeTrue();

    // The spoofs.
    expect($matches('evil.example.com'))->toBeFalse();
    expect($matches('halalbizs2.0.weststar-dev.com.evil.example.com'))->toBeFalse();
});
