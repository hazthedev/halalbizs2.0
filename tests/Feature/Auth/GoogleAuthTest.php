<?php

use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
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

function mockGoogleUser(string $id = 'google-123', string $email = 'aisha@example.com', string $name = 'Aisha binti Ali', bool $emailVerified = true): void
{
    $socialiteUser = (new SocialiteUser)->setRaw([
        'email_verified' => $emailVerified,
    ])->map([
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

test('a new google user is not created until they tick consent', function () {
    enableGoogle();
    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('auth.google.consent'));

    expect(User::count())->toBe(0);
    $this->assertGuest();

    $this->get(route('auth.google.consent'))
        ->assertOk()
        ->assertSee('aisha@example.com')
        ->assertSee(__('privacy policy'));

    // No tick: still no account.
    $this->post(route('auth.google.consent.store'))
        ->assertSessionHasErrors('terms');

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('a new google user who ticks consent gets a verified buyer account with consent proof', function () {
    enableGoogle();
    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake');

    $this->post(route('auth.google.consent.store'), ['terms' => '1'])
        ->assertRedirect(route('home'));

    $user = User::where('email', 'aisha@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Aisha binti Ali')
        ->and($user->google_id)->toBe('google-123')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('buyer'))->toBeTrue()
        ->and($user->privacy_consented_at)->not->toBeNull()
        ->and($user->privacy_notice_version)->toBe(config('app.privacy_notice_version'))
        ->and(session()->has('google_signup'))->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

test('the consent page without a pending google sign-up creates nothing', function () {
    enableGoogle();

    $this->get(route('auth.google.consent'))->assertRedirect(route('login'));
    $this->post(route('auth.google.consent.store'), ['terms' => '1'])->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('an account made for the google email while consent was open is signed into, not duplicated', function () {
    enableGoogle();
    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake');

    $user = User::factory()->create(['email' => 'aisha@example.com']);
    $user->assignRole('buyer');

    $this->post(route('auth.google.consent.store'), ['terms' => '1'])
        ->assertRedirect(route('home'));

    expect(User::count())->toBe(1)
        ->and($user->fresh()->google_id)->toBe('google-123');
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
        ->and($user->fresh()->google_id)->toBe('google-123')
        // Existing accounts get no consent step and keep their consent fields as they were.
        ->and($user->fresh()->privacy_consented_at)->toBeNull()
        ->and($user->fresh()->privacy_notice_version)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('an unverified google email cannot link to an existing account', function () {
    enableGoogle();

    $user = User::factory()->create(['email' => 'aisha@example.com']);
    $user->assignRole('buyer');

    mockGoogleUser(emailVerified: false);

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->fresh()->google_id)->toBeNull();
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

test('google linking an unverified pre-registered account drops what the earlier registrant holds', function () {
    enableGoogle();

    // Someone registered the owner's email first (API register gives a token
    // before the email is proven), then the real owner signs in with Google.
    $user = User::factory()->unverified()->create([
        'email' => 'aisha@example.com',
        'password' => 'squatter-password',
        'two_factor_method' => 'totp',
        'two_factor_secret' => (new Totp)->generateSecret(),
    ]);
    $user->assignRole('buyer');
    $user->createToken('mobile');
    $user->deviceTokens()->create(['token' => 'squatter-fcm', 'platform' => 'android']);

    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    $user->refresh();
    expect($user->google_id)->toBe('google-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and($user->deviceTokens()->count())->toBe(0)
        ->and(Hash::check('squatter-password', $user->password))->toBeFalse()
        ->and($user->hasTwoFactor())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

test('google linking an already verified account keeps its app sign-ins', function () {
    enableGoogle();

    $user = User::factory()->create(['email' => 'aisha@example.com']);
    $user->assignRole('buyer');
    $user->createToken('mobile');

    mockGoogleUser();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    expect($user->tokens()->count())->toBe(1);
});

test('a new google user with an unverified google email is not marked verified (HB-G227)', function () {
    enableGoogle();
    mockGoogleUser(emailVerified: false);

    $this->get(route('auth.google.callback').'?code=fake&state=fake');
    $this->post(route('auth.google.consent.store'), ['terms' => '1']);

    $user = User::where('email', 'aisha@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->google_id)->toBe('google-123');
});

test('an unverified google user signing in again keeps their sign-ins and stays unverified', function () {
    // Matched by google_id, not email: Google still does not vouch for the
    // address, so nothing is "proven" and nobody's credentials are dropped.
    enableGoogle();

    $user = User::factory()->unverified()->create([
        'email' => 'aisha@example.com',
        'google_id' => 'google-123',
        'password' => 'own-password',
    ]);
    $user->assignRole('buyer');
    $user->createToken('mobile');

    mockGoogleUser(emailVerified: false);

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    $user->refresh();
    expect($user->email_verified_at)->toBeNull()
        ->and($user->tokens()->count())->toBe(1)
        ->and(Hash::check('own-password', $user->password))->toBeTrue();
});

test('a squatter google account on the owner email loses its google link once the verified owner signs in', function () {
    enableGoogle();

    // First callback: a squatter whose Google email is NOT verified.
    // Second callback: the real owner, Google vouches for the same address.
    $googleUser = fn (string $id, bool $verified) => (new SocialiteUser)
        ->setRaw(['email_verified' => $verified])
        ->map(['id' => $id, 'email' => 'aisha@example.com', 'name' => 'Aisha binti Ali', 'nickname' => null]);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($googleUser('google-squatter', false), $googleUser('google-owner', true));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback').'?code=fake&state=fake');
    $this->post(route('auth.google.consent.store'), ['terms' => '1']);
    auth()->logout();

    $this->get(route('auth.google.callback').'?code=fake&state=fake')
        ->assertRedirect(route('home'));

    $user = User::where('email', 'aisha@example.com')->sole();
    expect($user->google_id)->toBe('google-owner')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});
