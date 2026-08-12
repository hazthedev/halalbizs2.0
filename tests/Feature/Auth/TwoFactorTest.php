<?php

use App\Enums\TwoFactorMethod;
use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\Register;
use App\Livewire\Storefront\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * Pull the most recent 2FA code out of the faked notification channel.
 */
function latestTwoFactorCode(User $user): string
{
    $codes = [];

    Notification::assertSentTo($user, TwoFactorCodeNotification::class, function (TwoFactorCodeNotification $notification) use (&$codes) {
        $codes[] = $notification->code;

        return true;
    });

    return end($codes);
}

test('email 2FA full flow: challenge, lockout after five wrong codes, reissue, success', function () {
    Notification::fake();

    $user = User::factory()->create(['two_factor_method' => 'email']);

    // Password passes → parked on the challenge, still a guest.
    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session('two_factor:user_id'))->toBe($user->id)
        ->and($user->otpCodes()->where('purpose', '2fa-email')->count())->toBe(1);

    $code = latestTwoFactorCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    // Five wrong attempts burn the code.
    $challenge = Livewire::test(TwoFactorChallenge::class);

    foreach (range(1, 5) as $attempt) {
        $challenge->set('code', $wrong)->call('verify')->assertHasErrors(['code']);
        $this->assertGuest();
    }

    expect($user->otpCodes()->count())->toBe(0);

    // The burned code is gone — even the correct one fails now.
    $this->travel(61)->seconds(); // past the per-minute throttles
    $challenge->set('code', $code)->call('verify')->assertHasErrors(['code']);
    $this->assertGuest();

    // Reissue → the fresh code logs the user in.
    $challenge->call('resend')->assertHasNoErrors();

    $challenge->set('code', latestTwoFactorCode($user))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(session()->has('two_factor:user_id'))->toBeFalse();
});

test('email 2FA resend is throttled to once per minute', function () {
    Notification::fake();

    $user = User::factory()->create(['two_factor_method' => 'email']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login'); // issues the first code

    Livewire::test(TwoFactorChallenge::class)
        ->call('resend')
        ->assertHasErrors(['code']);

    Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
});

test('TOTP 2FA: a code computed with the same RFC 6238 algorithm logs in', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    $user = User::factory()->create([
        'two_factor_method' => 'totp',
        'two_factor_secret' => $secret,
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $totp->code($secret))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('a TOTP code accepted once is rejected on replay', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    $user = User::factory()->create([
        'two_factor_method' => 'totp',
        'two_factor_secret' => $secret,
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    $code = $totp->code($secret);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);

    // Same user, fresh challenge, same code — still inside the ±1 period
    // TOTP window, so Totp::verify() alone would happily accept it again.
    auth()->logout();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $code)
        ->call('verify')
        ->assertHasErrors(['code']);

    $this->assertGuest();
});

test('a recovery code works exactly once', function () {
    $user = User::factory()->create([
        'two_factor_method' => 'totp',
        'two_factor_secret' => (new Totp)->generateSecret(),
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecovery')
        ->set('recovery_code', 'AAAAA-BBBBB')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_recovery_codes)->toBe(['CCCCC-DDDDD']);

    // Same code again on a fresh login → refused.
    auth()->logout();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    Livewire::test(TwoFactorChallenge::class)
        ->call('toggleRecovery')
        ->set('recovery_code', 'AAAAA-BBBBB')
        ->call('verify')
        ->assertHasErrors(['recovery_code']);

    $this->assertGuest();
});

test('the challenge bounces straight to login when nothing is pending', function () {
    Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
});

test('enabling email 2FA from the profile requires a correct emailed code', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('buyer');

    $profile = Livewire::actingAs($user)->test(Profile::class)
        ->call('startEmailTwoFactor')
        ->assertHasNoErrors();

    $profile->set('email_setup_code', '999999')->call('confirmEmailTwoFactor');

    // One wrong guess doesn't enable anything.
    expect($user->fresh()->two_factor_method)->toBeNull();

    $profile->set('email_setup_code', latestTwoFactorCode($user))
        ->call('confirmEmailTwoFactor')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_method)->toBe(TwoFactorMethod::Email);
});

test('enabling TOTP from the profile confirms a code and hands out ten single-use recovery codes', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');

    $profile = Livewire::actingAs($user)->test(Profile::class)->call('startTotpSetup');

    $secret = $profile->get('totpSecret');
    expect($secret)->toBeString()->toHaveLength(32);

    $profile->set('totp_setup_code', (new Totp)->code($secret))
        ->call('confirmTotpSetup')
        ->assertHasNoErrors();

    $fresh = $user->fresh();

    expect($fresh->two_factor_method)->toBe(TwoFactorMethod::Totp)
        ->and($fresh->two_factor_secret)->toBe($secret)
        ->and($fresh->two_factor_recovery_codes)->toHaveCount(10)
        ->and($profile->get('freshRecoveryCodes'))->toBe($fresh->two_factor_recovery_codes);
});

test('disabling 2FA requires the current password', function () {
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('buyer');

    Livewire::actingAs($user)->test(Profile::class)
        ->set('disable_password', 'wrong-password')
        ->call('disableTwoFactor')
        ->assertHasErrors(['disable_password']);

    expect($user->fresh()->two_factor_method)->toBe(TwoFactorMethod::Email);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('disable_password', 'password')
        ->call('disableTwoFactor')
        ->assertHasNoErrors();

    expect($user->fresh()->two_factor_method)->toBeNull()
        ->and($user->fresh()->two_factor_secret)->toBeNull()
        ->and($user->fresh()->two_factor_recovery_codes)->toBeNull();
});

test('admins without 2FA are redirected to the profile security section', function () {
    $admin = User::factory()->create();
    makeAdmin($admin);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertRedirect(route('account.profile').'#security');

    expect(session('toast')['message'])
        ->toBe(__('Set up two-factor authentication to access the admin panel.'));
});

test('admins with 2FA reach the admin panel', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('registration is rate limited after the threshold', function () {
    Notification::fake();

    foreach (range(1, 5) as $i) {
        Livewire::test(Register::class)
            ->set('name', 'Rate Limit Test')
            ->set('email', "rate-limit-{$i}@example.com")
            ->set('password', 'kx7-mangosteen-quay-2026')
            ->set('password_confirmation', 'kx7-mangosteen-quay-2026')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        auth()->logout(); // register() logs the new user in
    }

    // A 6th attempt from the same IP is blocked before it ever touches
    // validation or creates a row — this is what bounds unbounded account
    // creation now that Turnstile is dormant without configured keys.
    Livewire::test(Register::class)
        ->set('name', 'One Too Many')
        ->set('email', 'rate-limit-6@example.com')
        ->set('password', 'kx7-mangosteen-quay-2026')
        ->set('password_confirmation', 'kx7-mangosteen-quay-2026')
        ->set('terms', true)
        ->call('register')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'rate-limit-6@example.com')->exists())->toBeFalse();
});

// H-2: the only 2FA limiter was keyed on ('two-factor:'.$id.'|'.$ip), and the
// IP is attacker-chosen behind the trusted X-Forwarded-For proxy — so a rotated
// address bought a fresh bucket of 5 and nothing ever counted failures against
// the ACCOUNT. TOTP had no other ceiling: unlike the email branch, a wrong code
// costs the attacker nothing.
test('TOTP 2FA: guesses are capped per USER even when the source IP changes', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    $user = User::factory()->create([
        'two_factor_method' => 'totp',
        'two_factor_secret' => $secret,
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    // Each iteration = a request from a NEW source address. The test client has
    // one IP, so clearing the per-IP bucket is how that is expressed here — it
    // neutralises the OTHER limiter so this asserts on the per-user one only.
    for ($i = 0; $i < 20; $i++) {
        RateLimiter::clear('two-factor:'.$user->id.'|'.request()->ip());

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');

        expect(session('two_factor:user_id'))->toBe($user->id);
    }

    // The 21st is refused by the account-wide ceiling, and the parked challenge
    // is ended rather than left open to wait out the window.
    RateLimiter::clear('two-factor:'.$user->id.'|'.request()->ip());

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertRedirect(route('login'));

    expect(session()->has('two_factor:user_id'))->toBeFalse();
    $this->assertGuest();

    // The screen itself is gone: it no longer even mounts, so a correct code has
    // nowhere to be entered until they get past the login form again.
    Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
});
