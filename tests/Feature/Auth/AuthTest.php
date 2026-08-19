<?php

use App\Livewire\Storefront\Auth\ForgotPassword;
use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\Register;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Livewire\Storefront\Auth\VerifyEmailNotice;
use App\Models\User;
use App\Settings\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

/**
 * The breach check is a live k-anonymity lookup, so it is faked here: the suffix
 * below is the SHA-1 of "12345678" minus its 5-char prefix. Faking it means the
 * assertion holds with no network and cannot go quiet in CI — `uncompromised()`
 * FAILS OPEN when the API is unreachable, so a test that relied on the real
 * endpoint would silently stop testing anything the day CI lost egress.
 */
const PWNED_12345678 = 'FB2927D828AF22F592134E8932480637C0D:24230577';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('auth pages render', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['login', 'register', 'password.request']);

test('register creates a buyer and redirects to the verification notice', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'kx7-mangosteen-quay-2026')
        ->set('password_confirmation', 'kx7-mangosteen-quay-2026')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'aisha@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('buyer'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('register refuses a password that has appeared in a breach', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(PWNED_12345678)]);

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', '12345678')
        ->set('password_confirmation', '12345678')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register')
        ->assertHasErrors(['password']);

    expect(User::where('email', 'aisha@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('reset password refuses a password that has appeared in a breach', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(PWNED_12345678)]);

    $user = User::factory()->create(['email' => 'aisha@example.com']);
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token, 'email' => $user->email])
        ->set('password', '12345678')
        ->set('password_confirmation', '12345678')
        ->call('resetPassword')
        ->assertHasErrors(['password']);

    // The rule is only worth anything if it stops the WRITE, not just the render.
    expect(Hash::check('12345678', $user->fresh()->password))->toBeFalse();
});

test('register requires ToS consent', function () {
    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'kx7-mangosteen-quay-2026')
        ->set('password_confirmation', 'kx7-mangosteen-quay-2026')
        ->set('terms', false)
        ->call('register')
        ->assertHasErrors(['terms']);

    $this->assertGuest();
});

test('login authenticates and redirects home', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('suspended users cannot log in', function () {
    $user = User::factory()->create(['status' => 'suspended']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Your account has been suspended.');

    $this->assertGuest();
});

test('login is rate limited after 5 attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    // 6th attempt is blocked even with the correct password.
    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Too many login attempts')
        ->assertSee('seconds');

    $this->assertGuest();
});

test('forgot password always shows the generic status', function () {
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSee('If that email exists, a reset link is on the way.');

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendResetLink')
        ->assertSee('If that email exists, a reset link is on the way.');
});

test('reset password updates the password and redirects to login', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('verification notice resends once per minute', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Livewire::actingAs($user)
        ->test(VerifyEmailNotice::class)
        ->call('resend')
        ->assertHasNoErrors()
        ->call('resend')
        ->assertHasErrors(['resend']);

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('verified users are redirected away from the verification notice', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(VerifyEmailNotice::class)
        ->assertRedirect(route('home'));
});

/*
|--------------------------------------------------------------------------
| The breach check is admin-switchable (security.breached_password_check)
|--------------------------------------------------------------------------
| The three above prove the rule fires while it is ON. These prove the toggle
| actually reaches it, and — the one that matters — that turning it off does
| NOT take the length floor with it. Switching off the breach list must not
| quietly restore the min(8)-only rule that let the live superadmin run
| `12345678` before 2026-08-10.
*/

/** Turn the breach check off, the way the admin screen does. */
function disableBreachedPasswordCheck(): void
{
    $settings = app(SecuritySettings::class);
    $settings->breached_password_check = false;
    $settings->save();
}

test('the breach check is ON by default', function () {
    expect(app(SecuritySettings::class)->breached_password_check)->toBeTrue();
});

test('with the check off, a breached password is accepted', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(PWNED_12345678)]);
    disableBreachedPasswordCheck();

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', '12345678')
        ->set('password_confirmation', '12345678')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors(['password']);

    expect(User::where('email', 'aisha@example.com')->exists())->toBeTrue();
});

test('with the check off, the API is not called at all', function () {
    // Outcome alone cannot tell "the rule was skipped" from "the rule ran and
    // the fake happened to say clean". The absence of the request can.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(PWNED_12345678)]);
    disableBreachedPasswordCheck();

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', '12345678')
        ->set('password_confirmation', '12345678')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register');

    Http::assertNothingSent();
});

test('with the check off, the 8-character minimum still holds', function () {
    // The toggle turns off the breach LIST, not the length floor. If this ever
    // goes green with a 7-character password, the toggle has become a switch
    // for "no password rules at all".
    disableBreachedPasswordCheck();

    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'short7c')
        ->set('password_confirmation', 'short7c')
        ->set('phone', '012-345 6789')
        ->set('terms', true)
        ->call('register')
        ->assertHasErrors(['password']);

    expect(User::where('email', 'aisha@example.com')->exists())->toBeFalse();
});
