<?php

use App\Enums\TwoFactorMethod;
use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
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
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertRedirect(route('account.profile').'#security');

    expect(session('toast')['message'])
        ->toBe(__('Set up two-factor authentication to access the admin panel.'));
});

test('admins with 2FA reach the admin panel', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
});
