<?php

use App\Models\User;
use App\Services\AffiliateService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('security headers are present on web responses', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain('challenges.cloudflare.com')
        ->toContain("frame-ancestors 'self'")
        ->toContain("form-action 'self' https://sandbox.ipay88.com.my https://payment.ipay88.com.my")
        ->toContain("object-src 'none'");
});

test('security headers are present on a 404 response', function () {
    // Unmatched URI — Laravel throws NotFoundHttpException at route-dispatch
    // time. AL-C2: SecurityHeaders used to be appended (innermost), so it
    // never saw this response come back through; it's now prepended
    // (outermost, alongside HandleUrlRedirects) so it wraps the whole
    // pipeline instead of just the route handler.
    $response = $this->get('/this-route-does-not-exist-security-headers-test');

    $response->assertNotFound()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'");
});

test('security headers are present on a 419 CSRF token mismatch response', function () {
    // ValidateCsrfToken auto-bypasses whenever Application::runningUnitTests()
    // is true, which is how this whole suite runs (APP_ENV=testing) — so a
    // real mismatch can only be observed here by flipping that flag off for
    // one request, then restoring it.
    app()->instance('env', 'production');

    $response = $this->post('/newsletter', []);

    app()->instance('env', 'testing');

    $response->assertStatus(419)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'");
});

test('the affiliate redirect rejects a protocol-relative offsite target', function () {
    // AL-C1: `//evil.com` starts with '/' just like a real in-app path, so a
    // naive str_starts_with($to, '/') check passed it through and browsers
    // resolve `Location: //evil.com` as an offsite redirect.
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());

    $this->get('/r/'.$affiliate->code.'?to=//evil.com')->assertRedirect(route('home'));
});
