<?php

use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Auth\Login;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use App\Services\DeviceGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const POLISH_UA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const POLISH_UA_FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';

function polishDeviceRequest(string $agent = POLISH_UA_CHROME, string $ip = '203.0.113.10'): Request
{
    return Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => $agent,
        'REMOTE_ADDR' => $ip,
    ]);
}

// ── New-device alert ─────────────────────────────────────────────────────

test('the first ever login records the device silently', function () {
    Notification::fake();

    $user = User::factory()->create();

    app(DeviceGuard::class)->record($user, polishDeviceRequest());

    expect($user->knownDevices()->count())->toBe(1)
        ->and($user->knownDevices()->first()->label)->toBe('Chrome on Windows');

    Notification::assertNothingSent();
});

test('a login from a second, unseen device alerts exactly once', function () {
    Notification::fake();

    $user = User::factory()->create();
    $guard = app(DeviceGuard::class);

    $guard->record($user, polishDeviceRequest());                          // first device — silent
    $guard->record($user, polishDeviceRequest(POLISH_UA_FIREFOX, '198.51.100.7')); // unseen → alert
    $guard->record($user, polishDeviceRequest(POLISH_UA_FIREFOX, '198.51.100.7')); // seen → silent

    expect($user->knownDevices()->count())->toBe(2);

    Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 1);
});

test('the same browser inside the same /24 block does not re-alert', function () {
    Notification::fake();

    $user = User::factory()->create();
    $guard = app(DeviceGuard::class);

    $guard->record($user, polishDeviceRequest(ip: '203.0.113.10'));
    $guard->record($user, polishDeviceRequest(ip: '203.0.113.250')); // DHCP shuffle, same block

    expect($user->knownDevices()->count())->toBe(1);

    Notification::assertNothingSent();
});

test('a successful password login records the device without alerting a fresh account', function () {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->knownDevices()->count())->toBe(1);

    Notification::assertNothingSent();
});

test('a password login from an unseen device sends the alert', function () {
    Notification::fake();

    $user = User::factory()->create();

    // The account has logged in from a (different) device before.
    $user->knownDevices()->create([
        'fingerprint' => hash('sha256', 'some-other-device'),
        'label' => 'Safari on macOS',
        'last_seen_at' => now()->subWeek(),
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    expect($user->knownDevices()->count())->toBe(2);

    Notification::assertSentTo($user, NewDeviceLoginNotification::class);
});

// ── Active sessions ──────────────────────────────────────────────────────

function polishSessionRow(User $user, string $id, string $agent, int $lastActivity): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.7',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => $lastActivity,
    ]);
}

test('the profile lists active sessions with a this-device marker', function () {
    $user = User::factory()->create();

    polishSessionRow($user, session()->getId(), POLISH_UA_CHROME, now()->unix());
    polishSessionRow($user, 'other-session-id', POLISH_UA_FIREFOX, now()->subHour()->unix());

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSee('Active sessions')
        ->assertSee('Chrome on Windows')
        ->assertSee('Firefox on Linux')
        ->assertSee('This device');
});

test('log out other devices needs the password and removes the other sessions', function () {
    $user = User::factory()->create();

    polishSessionRow($user, session()->getId(), POLISH_UA_CHROME, now()->unix());
    polishSessionRow($user, 'other-session-id', POLISH_UA_FIREFOX, now()->subHour()->unix());

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('logout_others_password', 'wrong-password')
        ->call('logoutOtherDevices')
        ->assertHasErrors(['logout_others_password']);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('logout_others_password', 'password')
        ->call('logoutOtherDevices')
        ->assertHasNoErrors();

    $remaining = DB::table('sessions')->where('user_id', $user->id)->pluck('id');

    expect($remaining)->not->toContain('other-session-id');
});
