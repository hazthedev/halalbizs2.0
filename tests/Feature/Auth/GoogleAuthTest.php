<?php

use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function enableGoogle(): void
{
    $settings = app(SecuritySettings::class);
    $settings->google_client_id = 'test-client-id.apps.googleusercontent.com';
    $settings->google_client_secret = 'test-client-secret';
    $settings->save();
}

function mockGoogleUser(string $id = 'google-123', string $email = 'aisha@example.com', string $name = 'Aisha binti Ali'): void
{
    $socialiteUser = (new SocialiteUser)->map([
        'id' => $id,
        'email' => $email,
        'name' => $name,
        'nickname' => null,
    ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('google sign-in is dormant when unconfigured: no button, endpoints 404', function () {
    $this->get(route('login'))->assertOk()->assertDontSee(__('Continue with Google'));
    $this->get(route('register'))->assertOk()->assertDontSee(__('Continue with Google'));

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
});

test('configured google sign-in shows the button and redirects to Google', function () {
    enableGoogle();

    $this->get(route('login'))->assertOk()->assertSee(__('Continue with Google'));
    $this->get(route('register'))->assertOk()->assertSee(__('Continue with Google'));

    $this->get(route('auth.google.redirect'))
        ->assertRedirectContains('accounts.google.com');
});

test('google callback creates a verified buyer and logs them in', function () {
    enableGoogle();
    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    $user = User::where('email', 'aisha@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Aisha binti Ali')
        ->and($user->google_id)->toBe('google-123')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('buyer'))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('google callback links an existing account by email', function () {
    enableGoogle();

    $user = User::factory()->create(['email' => 'aisha@example.com']);
    $user->assignRole('buyer');

    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    expect(User::count())->toBe(1)
        ->and($user->fresh()->google_id)->toBe('google-123');

    $this->assertAuthenticatedAs($user);
});

test('google users with 2FA still face the challenge', function () {
    enableGoogle();

    $user = User::factory()->create([
        'email' => 'aisha@example.com',
        'two_factor_method' => 'totp',
        'two_factor_secret' => (new Totp)->generateSecret(),
    ]);
    $user->assignRole('buyer');

    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session('two_factor:user_id'))->toBe($user->id);
});

test('suspended accounts cannot enter through google', function () {
    enableGoogle();

    $user = User::factory()->create([
        'email' => 'aisha@example.com',
        'status' => 'suspended',
    ]);

    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
});
