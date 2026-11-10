<?php

use App\Livewire\Admin\Buyers\Detail;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** Pull the most recent 2FA code out of the faked notification channel. */
function apiLatestTwoFactorCode(User $user): string
{
    $codes = [];

    Notification::assertSentTo($user, TwoFactorCodeNotification::class, function (TwoFactorCodeNotification $notification) use (&$codes) {
        $codes[] = $notification->code;

        return true;
    });

    return end($codes);
}

function apiLogin(User $user, string $password = 'password')
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'pest-phone',
    ]);
}

// ── Register ────────────────────────────────────────────────────────────

test('register creates a buyer, sends the verification email and returns a token', function () {
    Notification::fake();
    Http::fake(); // the breach check: an empty HIBP answer means "not compromised"

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Aisha binti Ali',
        'email' => 'aisha@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'device_name' => 'pest-phone',
    ]);

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.user.email', 'aisha@example.com')
        ->assertJsonPath('data.user.email_verified', false)
        ->assertJsonPath('data.user.roles', ['buyer']);

    $user = User::where('email', 'aisha@example.com')->firstOrFail();

    expect($user->hasRole('buyer'))->toBeTrue()
        ->and($user->tokens()->where('name', 'pest-phone')->count())->toBe(1);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('register rejects a weak password with the validation envelope', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Aisha',
        'email' => 'aisha@example.com',
        'password' => 'short7c',
        'password_confirmation' => 'short7c',
        'device_name' => 'pest-phone',
    ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['password']]]);

    expect(User::where('email', 'aisha@example.com')->exists())->toBeFalse();
});

// ── Login ───────────────────────────────────────────────────────────────

test('login returns a token and the user', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');

    apiLogin($user)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'email_verified', 'roles', 'store', 'unread_notifications', 'coins']]]);

    expect($user->tokens()->count())->toBe(1);
});

test('login with the wrong password is a 422 on the email field', function () {
    $user = User::factory()->create();

    apiLogin($user, 'nope-nope-nope')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.fields.email.0', __('These details don\'t match our records — check your email and password.'));

    expect($user->tokens()->count())->toBe(0);
});

test('login is throttled after five attempts for one email', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        apiLogin($user, 'wrong-password')->assertStatus(422);
    }

    apiLogin($user)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'throttled')
        ->assertHeader('Retry-After');
});

test('a suspended user cannot log in', function () {
    $user = User::factory()->create();
    $user->forceFill(['status' => 'suspended'])->save();

    apiLogin($user)
        ->assertStatus(422)
        ->assertJsonPath('error.fields.email.0', __('Your account has been suspended.'));

    expect($user->tokens()->count())->toBe(0);
});

test('suspending a user ends their bearer sessions: the admin screen deletes the tokens, and a leftover token is refused', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $token = apiLogin($user)->assertOk()->json('data.token');

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    // The admin screen revokes every token outright...
    $admin = makeAdmin(User::factory()->create());
    Livewire::actingAs($admin)
        ->test(Detail::class, ['user' => $user])
        ->set('suspendReason', 'Abuse pattern on returns')
        ->call('suspend')
        ->assertHasNoErrors();

    expect($user->tokens()->count())->toBe(0);

    // ...and a token minted some other way dies on its first request.
    $leftover = $user->createToken('leftover')->plainTextToken;

    app('auth')->forgetGuards();
    $this->withToken($leftover)->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');

    expect($user->tokens()->count())->toBe(0);
});

// ── Two-factor ──────────────────────────────────────────────────────────

test('email 2FA: login answers 202 with a challenge, wrong codes 422, the right code a token', function () {
    Notification::fake();

    $user = User::factory()->create(['two_factor_method' => 'email']);

    $response = apiLogin($user)
        ->assertStatus(202)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.two_factor.method', 'email')
        ->assertJsonMissingPath('data.token');

    $challenge = $response->json('data.two_factor.challenge');
    $code = apiLatestTwoFactorCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $wrong, 'device_name' => 'pest-phone'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['code']]]);

    expect($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $code, 'device_name' => 'pest-phone'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token']]);

    expect($user->tokens()->count())->toBe(1);

    // The challenge is single-use.
    $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $code, 'device_name' => 'pest-phone'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['challenge']]]);
});

test('TOTP 2FA: five wrong codes forget the challenge; a fresh challenge accepts the RFC 6238 code', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    $user = User::factory()->create(['two_factor_method' => 'totp', 'two_factor_secret' => $secret]);

    $challenge = apiLogin($user)->assertStatus(202)->json('data.two_factor.challenge');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '000000', 'device_name' => 'pest-phone'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['code']]]);
    }

    // Sixth try: the challenge itself is gone, even with the right code.
    $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $totp->code($secret), 'device_name' => 'pest-phone'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['challenge']]]);

    $this->travel(61)->seconds(); // past the per-minute login limiter

    $challenge = apiLogin($user)->assertStatus(202)->json('data.two_factor.challenge');

    $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => $totp->code($secret), 'device_name' => 'pest-phone'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token']]);
});

// ── Logout, forgot password, resend ─────────────────────────────────────

test('logout revokes only the current token', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('phone')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('ok', true);

    expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);

    app('auth')->forgetGuards(); // one app per test: drop the guard's cached user
    $this->withToken($phone)->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('forgot password always answers 200 and sends the reset link when the account exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->assertJsonPath('ok', true);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('ok', true);

    Notification::assertSentTo($user, ResetPassword::class);

    // Five per hour per (email, connection), then 429.
    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    }
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'throttled');
});

test('verification resend sends the email for an unverified user only', function () {
    Notification::fake();

    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->withToken($unverified->createToken('p')->plainTextToken)
        ->postJson('/api/v1/auth/verification/resend')
        ->assertOk()
        ->assertJsonPath('data.verified', false);

    app('auth')->forgetGuards(); // one app per test: drop the guard's cached user
    $this->withToken($verified->createToken('p')->plainTextToken)
        ->postJson('/api/v1/auth/verification/resend')
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    Notification::assertSentTo($unverified, VerifyEmail::class);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});
