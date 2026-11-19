<?php

/**
 * HB-NEWDEV-2FA (Haze 2026-11-19): admins and sellers signing in from a
 * device we have not seen must enter an emailed code first. Buyers keep the
 * alert email only. A user's first ever device stays silent.
 */

use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\DeviceGuard;
use App\Settings\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

function newDevUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Known device = the fingerprint the Livewire test harness produces (default UA + 127.0.0.1). */
function newDevKnowHarness(User $user): void
{
    $user->knownDevices()->create([
        'fingerprint' => app(DeviceGuard::class)->fingerprint(Request::create('/login', 'GET')),
        'label' => 'Harness',
        'last_seen_at' => now()->subDay(),
    ]);
}

/** Some other device the account used before. */
function newDevKnowOther(User $user): void
{
    $user->knownDevices()->create([
        'fingerprint' => hash('sha256', 'some-other-device'),
        'label' => 'Safari on macOS',
        'last_seen_at' => now()->subWeek(),
    ]);
}

function newDevLogin(User $user, string $context = 'storefront')
{
    return Livewire::test(Login::class, ['context' => $context])
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();
}

function newDevLatestCode(User $user): string
{
    $codes = [];
    Notification::assertSentTo($user, TwoFactorCodeNotification::class, function ($n) use (&$codes) {
        $codes[] = $n->code;

        return true;
    });

    return end($codes);
}

test('a seller on a known device logs in straight through', function () {
    $seller = newDevUser('seller');
    newDevKnowHarness($seller);

    newDevLogin($seller, 'seller');

    expect(auth()->id())->toBe($seller->id);
    Notification::assertNotSentTo($seller, TwoFactorCodeNotification::class);
});

test('a seller first ever device is not challenged', function () {
    $seller = newDevUser('seller');

    newDevLogin($seller, 'seller');

    expect(auth()->id())->toBe($seller->id)
        ->and($seller->knownDevices()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('a seller on a new device is held at the code page, then gets in with the right code', function () {
    $seller = newDevUser('seller');
    newDevKnowOther($seller);

    newDevLogin($seller, 'seller')->assertRedirect(route('two-factor.challenge'));

    expect(auth()->check())->toBeFalse()
        ->and($seller->knownDevices()->count())->toBe(1);

    $this->get('/seller')->assertRedirect(route('seller.login'));

    Livewire::test(TwoFactorChallenge::class)
        ->assertSee(__('You\'re signing in from a new device. Enter the code we emailed to confirm it\'s you.'))
        ->assertDontSee(__('Trust this device for 30 days'))
        ->set('code', newDevLatestCode($seller))
        ->call('verify')
        ->assertHasNoErrors();

    expect(auth()->id())->toBe($seller->id)
        ->and($seller->knownDevices()->count())->toBe(2);
    // The code email replaces the new-device alert: no double mail.
    Notification::assertNotSentTo($seller, NewDeviceLoginNotification::class);
});

test('five wrong codes lock a new-device seller out', function () {
    $seller = newDevUser('seller');
    newDevKnowOther($seller);

    newDevLogin($seller, 'seller')->assertRedirect(route('two-factor.challenge'));
    $code = newDevLatestCode($seller);
    $wrong = $code === '000000' ? '111111' : '000000';

    $challenge = Livewire::test(TwoFactorChallenge::class);

    foreach (range(1, 5) as $_) {
        $challenge->set('code', $wrong)->call('verify')->assertHasErrors('code');
    }

    $challenge->set('code', $code)->call('verify')->assertHasErrors('code');

    expect(auth()->check())->toBeFalse()
        ->and($seller->knownDevices()->count())->toBe(1);
});

test('an admin on a new device is held at the code page, then gets in with the right code', function () {
    $admin = newDevUser('admin');
    newDevKnowOther($admin);

    newDevLogin($admin, 'admin')->assertRedirect(route('two-factor.challenge'));

    expect(auth()->check())->toBeFalse();
    $this->get('/admin')->assertRedirect();
    expect(auth()->check())->toBeFalse();

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', newDevLatestCode($admin))
        ->call('verify')
        ->assertHasNoErrors();

    expect(auth()->id())->toBe($admin->id);
    Notification::assertNotSentTo($admin, NewDeviceLoginNotification::class);
});

test('an admin on a known device logs in straight through', function () {
    $admin = newDevUser('admin');
    newDevKnowHarness($admin);

    newDevLogin($admin, 'admin');

    expect(auth()->id())->toBe($admin->id);
});

test('a buyer on a new device logs in straight through and gets the alert email', function () {
    $buyer = newDevUser('buyer');
    newDevKnowOther($buyer);

    newDevLogin($buyer)->assertRedirect(route('home'));

    expect(auth()->id())->toBe($buyer->id);
    Notification::assertSentTo($buyer, NewDeviceLoginNotification::class);
    Notification::assertNotSentTo($buyer, TwoFactorCodeNotification::class);
});

test('the app login asks a new-device seller for the emailed code', function () {
    $seller = newDevUser('seller');
    newDevKnowOther($seller);

    $res = $this->postJson('/api/v1/auth/login', [
        'email' => $seller->email, 'password' => 'password', 'device_name' => 'phone',
    ])->assertStatus(202)->assertJsonPath('data.two_factor.method', 'email');

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge' => $res->json('data.two_factor.challenge'),
        'code' => newDevLatestCode($seller),
        'device_name' => 'phone',
    ])->assertOk()->assertJsonStructure(['data' => ['token']]);

    expect($seller->knownDevices()->count())->toBe(2);
    Notification::assertNotSentTo($seller, NewDeviceLoginNotification::class);
});

test('google sign-in holds a new-device seller at the code page', function () {
    $settings = app(SecuritySettings::class);
    $settings->google_client_id = 'test-client-id.apps.googleusercontent.com';
    $settings->google_client_secret = 'test-client-secret';
    $settings->save();

    $seller = newDevUser('seller');
    $seller->forceFill(['google_id' => 'google-nd-1', 'email_verified_at' => now()])->save();
    newDevKnowOther($seller);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn((new Laravel\Socialite\Two\User)->setRaw(['email_verified' => true])->map([
        'id' => 'google-nd-1', 'email' => $seller->email, 'name' => $seller->name, 'nickname' => null,
    ]));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('two-factor.challenge'));

    expect(auth()->check())->toBeFalse();
    Notification::assertSentTo($seller, TwoFactorCodeNotification::class);
});

test('a seller locked out by five wrong codes gets back in with a resent code', function () {
    $seller = newDevUser('seller');
    newDevKnowOther($seller);

    newDevLogin($seller, 'seller')->assertRedirect(route('two-factor.challenge'));
    $first = newDevLatestCode($seller);
    $wrong = $first === '000000' ? '111111' : '000000';

    $challenge = Livewire::test(TwoFactorChallenge::class);

    foreach (range(1, 5) as $_) {
        $challenge->set('code', $wrong)->call('verify')->assertHasErrors('code');
    }

    // The per-minute (user, IP) bucket and the one-send-per-minute cap both clear.
    $this->travel(61)->seconds();

    $challenge->call('resend')->assertHasNoErrors();
    $fresh = newDevLatestCode($seller);

    $challenge->set('code', $fresh)->call('verify')->assertHasNoErrors();

    expect(auth()->id())->toBe($seller->id);
});

test('the held state cannot replay a Livewire action', function () {
    $admin = newDevUser('admin');
    $admin->forceFill(['email_verified_at' => now()])->save();

    preg_match('/wire:snapshot="([^"]*)"/', $this->actingAs($admin)->get('/admin')->assertOk()->getContent(), $m);
    $snapshot = html_entity_decode($m[1], ENT_QUOTES);

    auth()->logout();
    session()->put(['two_factor:user_id' => $admin->id, 'two_factor:new_device' => true]);

    $status = $this->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => $snapshot, 'updates' => (object) [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
    ])->status();

    expect($status)->toBeIn([401, 302, 403])
        ->and(auth()->check())->toBeFalse();
});

test('the app web sign-in link sends a new-device seller through the code first', function () {
    $seller = newDevUser('seller');
    newDevKnowOther($seller);

    $url = $this->withToken($seller->createToken('phone')->plainTextToken)
        ->postJson('/api/v1/me/web-sign-in-link', ['to' => 'seller-apply'])
        ->assertOk()->json('data.url');
    app('auth')->forgetGuards();
    $this->withoutToken();

    $this->post($url)->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest('web');
    app('auth')->shouldUse('web'); // the API call above left sanctum as the default guard in this process

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', newDevLatestCode($seller))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('seller.apply'));

    expect(auth()->id())->toBe($seller->id);
});

test('the app web sign-in link signs a seller straight in on a known browser', function () {
    $seller = newDevUser('seller');
    newDevKnowHarness($seller);

    $url = $this->withToken($seller->createToken('phone')->plainTextToken)
        ->postJson('/api/v1/me/web-sign-in-link', ['to' => 'seller-apply'])
        ->assertOk()->json('data.url');
    app('auth')->forgetGuards();
    $this->withoutToken();

    $this->post($url)->assertRedirect(route('seller.apply'));
    $this->assertAuthenticatedAs($seller, 'web');
    Notification::assertNotSentTo($seller, TwoFactorCodeNotification::class);
});
