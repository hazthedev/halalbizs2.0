<?php

/**
 * "Trust this device for 30 days" (App\Services\DeviceTrust).
 *
 * 2FA stays mandatory; trust only lets a user skip the CODE step on a device
 * they already verified. Trust needs BOTH a secret cookie token and a matching
 * device fingerprint, and only the token hash is stored.
 */

use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\DeviceTrust;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

function twoFactorUser(): User
{
    return User::factory()->create(['two_factor_method' => 'email']);
}

/** A request carrying a fixed UA + IP so the fingerprint is deterministic. */
function deviceRequest(string $ua = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120', string $ip = '203.0.113.4', array $cookies = []): Request
{
    return Request::create('/login', 'GET', server: ['HTTP_USER_AGENT' => $ua, 'REMOTE_ADDR' => $ip], cookies: $cookies);
}

/** The plaintext token from the trust cookie DeviceTrust::remember() queued. */
function queuedTrustToken(): string
{
    foreach (app('cookie')->getQueuedCookies() as $cookie) {
        if ($cookie->getName() === 'device_trust') {
            return $cookie->getValue();
        }
    }

    return '';
}

/** Most recent 2FA code from the faked mail channel (local to this file). */
function trustLatestCode(User $user): string
{
    $codes = [];
    Notification::assertSentTo($user, TwoFactorCodeNotification::class, function ($n) use (&$codes) {
        $codes[] = $n->code;

        return true;
    });

    return end($codes);
}

// ── The service in isolation ───────────────────────────────────────────────

test('a device is not trusted until remembered', function () {
    $user = twoFactorUser();
    expect(app(DeviceTrust::class)->isTrusted($user, deviceRequest()))->toBeFalse();
});

test('remember() makes THIS device trusted and returns a usable cookie token', function () {
    $user = twoFactorUser();
    $trust = app(DeviceTrust::class);

    $trust->remember($user, deviceRequest());

    // The queued cookie carries the plaintext token; the DB stores only its hash.
    $token = queuedTrustToken();

    $device = $user->knownDevices()->first();
    expect($device->trust_token_hash)->not->toBe($token)          // hashed, not plain
        ->and($device->trustIsLive())->toBeTrue();

    // A fresh request carrying that cookie from the same device is trusted.
    expect($trust->isTrusted($user, deviceRequest(cookies: ['device_trust' => $token])))->toBeTrue();
});

test('trust does not carry to a DIFFERENT device even with the same cookie', function () {
    $user = twoFactorUser();
    $trust = app(DeviceTrust::class);

    $trust->remember($user, deviceRequest(ip: '203.0.113.4'));
    $token = queuedTrustToken();

    // Same cookie, different network block → fingerprint mismatch → not trusted.
    $elsewhere = deviceRequest(ip: '198.51.100.9', cookies: ['device_trust' => $token]);
    expect($trust->isTrusted($user, $elsewhere))->toBeFalse();
});

test('an expired trust is not honoured', function () {
    $user = twoFactorUser();
    $trust = app(DeviceTrust::class);

    $trust->remember($user, deviceRequest());
    $token = queuedTrustToken();

    $user->knownDevices()->first()->update(['trusted_until' => now()->subDay()]);

    expect($trust->isTrusted($user, deviceRequest(cookies: ['device_trust' => $token])))->toBeFalse();
});

test('forgetAll() revokes trust on every device', function () {
    $user = twoFactorUser();
    $trust = app(DeviceTrust::class);

    $trust->remember($user, deviceRequest());
    $token = queuedTrustToken();

    $trust->forgetAll($user);

    expect($trust->isTrusted($user, deviceRequest(cookies: ['device_trust' => $token])))->toBeFalse()
        ->and($user->knownDevices()->first()->trust_token_hash)->toBeNull();
});

// ── The login flow honours it ──────────────────────────────────────────────

test('checking "trust this device" on the challenge skips the code next login', function () {
    $user = twoFactorUser();

    // First login: full 2FA, with the trust box ticked.
    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    $code = trustLatestCode($user);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $code)
        ->set('trustDevice', true)
        ->call('verify')
        ->assertRedirect(route('home'));

    // The device row is now trusted.
    expect($user->knownDevices()->first()->trustIsLive())->toBeTrue();
});

test('without trusting, the next login still requires the code', function () {
    $user = twoFactorUser();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge')); // no trust → parked, as before

    expect($user->knownDevices()->whereNotNull('trust_token_hash')->exists())->toBeFalse();
});

test('a trusted device skips the challenge entirely at login', function () {
    $user = twoFactorUser();

    // Trust the fingerprint the Livewire test harness itself produces (default
    // UA + 127.0.0.1), so the login below matches it and the cookie carries the
    // token. This exercises the actual Login::login skip branch, not just the
    // service.
    $harnessRequest = Request::create('/login', 'GET');
    app(DeviceTrust::class)->remember($user, $harnessRequest);
    $token = queuedTrustToken();

    Livewire::withCookies(['device_trust' => $token])
        ->test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('home')); // straight in — NOT parked on the challenge

    expect(auth()->check())->toBeTrue();
});
