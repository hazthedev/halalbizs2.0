<?php

use App\Livewire\Admin\System\Staff;
use App\Livewire\Seller\Settings;
use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Auth\Register;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Models\Store;
use App\Models\User;
use App\Settings\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

/*
 * HB-G226 (HB-DEC-1124e): users.password_set_at says whether the user ever
 * chose a password. Google sign-up stores a random one nobody knows, so the
 * #265 bank-change password step needs a hint for exactly those sellers.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::fake(); // breach check: empty answer = not compromised
    Notification::fake();
});

function psaGoogle(string $email = 'aisha@example.com'): void
{
    $settings = app(SecuritySettings::class);
    $settings->google_client_id = 'test-client-id.apps.googleusercontent.com';
    $settings->google_client_secret = 'test-client-secret';
    $settings->save();

    $socialiteUser = (new SocialiteUser)->setRaw(['email_verified' => true])
        ->map(['id' => 'google-123', 'email' => $email, 'name' => 'Aisha binti Ali', 'nickname' => null]);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

function psaSeller(bool $passwordSet): User
{
    $user = User::factory()->create(['password_set_at' => $passwordSet ? now() : null]);
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

const PSA_HINT = 'You signed up with Google and have not set a password yet. Use Forgot password to set one, then change your bank details.';

// ── Writers ────────────────────────────────────────────────────────────────

test('web register with a password sets password_set_at', function () {
    Livewire::test(Register::class)
        ->set('name', 'Aisha binti Ali')
        ->set('email', 'aisha@example.com')
        ->set('password', 'kx7-mangosteen-quay-2026')
        ->set('password_confirmation', 'kx7-mangosteen-quay-2026')
        ->set('terms', true)
        ->call('register')
        ->assertHasNoErrors();

    expect(User::where('email', 'aisha@example.com')->value('password_set_at'))->not->toBeNull();
});

test('api register with a password sets password_set_at', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Aisha binti Ali',
        'email' => 'aisha@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'device_name' => 'pest-phone',
        'terms' => true,
    ])->assertCreated()->assertJsonPath('data.user.password_set', true);

    expect(User::where('email', 'aisha@example.com')->value('password_set_at'))->not->toBeNull();
});

test('google sign-up leaves password_set_at empty', function () {
    psaGoogle();

    $this->get(route('auth.google.callback').'?code=fake&state=fake');
    $this->post(route('auth.google.consent.store'), ['terms' => '1'])->assertRedirect(route('home'));

    expect(User::where('email', 'aisha@example.com')->firstOrFail()->password_set_at)->toBeNull();
});

test('google linking a verified password account keeps password_set_at', function () {
    $user = User::factory()->create(['email' => 'aisha@example.com', 'password_set_at' => '2026-11-01 10:00:00']);
    $user->assignRole('buyer');
    psaGoogle();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')->assertRedirect(route('home'));

    expect($user->fresh()->password_set_at->toDateTimeString())->toBe('2026-11-01 10:00:00');
});

test('google taking over an unverified account clears password_set_at with the scrambled password', function () {
    $user = User::factory()->unverified()->create(['email' => 'aisha@example.com']);
    $user->assignRole('buyer');
    expect($user->password_set_at)->not->toBeNull();
    psaGoogle();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')->assertRedirect(route('home'));

    expect($user->fresh()->password_set_at)->toBeNull();
});

test('a password reset sets password_set_at', function () {
    $user = User::factory()->create(['password_set_at' => null]);
    $token = app('auth.password.broker')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'Str0ng!Passw0rd#2026')
        ->set('password_confirmation', 'Str0ng!Passw0rd#2026')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect($user->fresh()->password_set_at)->not->toBeNull();
});

test('a web password change sets password_set_at', function () {
    $user = User::factory()->create(['password_set_at' => null]);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->fresh()->password_set_at)->not->toBeNull();
});

test('an api password change sets password_set_at', function () {
    $user = User::factory()->create(['password_set_at' => null]);

    $this->withToken($user->createToken('pest-phone')->plainTextToken)->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'new-long-password',
        'password_confirmation' => 'new-long-password',
    ])->assertOk();

    expect($user->fresh()->password_set_at)->not->toBeNull();
});

test('an invited admin has no password set until they claim the reset link', function () {
    $inviter = makeAdmin(User::factory()->create(['is_superadmin' => true]));

    Livewire::actingAs($inviter)->test(Staff::class)
        ->set('showInvite', true)
        ->set('inviteName', 'New Admin')
        ->set('inviteEmail', 'new-admin@halalbizs.test')
        ->call('invite');

    expect(User::where('email', 'new-admin@halalbizs.test')->firstOrFail()->password_set_at)->toBeNull();
});

// ── Hint ───────────────────────────────────────────────────────────────────

test('the bank form shows the google hint with the reset link only to a seller without a password', function () {
    Livewire::actingAs(psaSeller(passwordSet: false))->test(Settings::class)
        ->assertSee(__(PSA_HINT))
        ->assertSee(route('password.request'));

    Livewire::actingAs(psaSeller(passwordSet: true))->test(Settings::class)
        ->assertDontSee(__(PSA_HINT));
});

test('a signed-in seller can open the forgot password page from the hint', function () {
    $this->actingAs(psaSeller(passwordSet: false))
        ->get(route('password.request'))
        ->assertOk();
});

test('GET me tells the app whether a password is set', function () {
    foreach ([true => true, false => false] as $set => $expected) {
        $user = psaSeller(passwordSet: (bool) $set);
        app('auth')->forgetGuards();

        $this->withToken($user->createToken('pest-phone')->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.password_set', $expected);
    }
});
