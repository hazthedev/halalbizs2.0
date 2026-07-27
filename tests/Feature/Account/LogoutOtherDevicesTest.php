<?php

/**
 * "Log out other devices" must actually kill the other device — including one
 * that logged in with "Keep me logged in". Reported live: laptop → log out
 * others → phone stayed logged in.
 *
 * Root cause: the action deleted other session ROWS but left the user's
 * remember_token untouched, so a remembered device re-authenticated from its
 * recaller cookie and a fresh session appeared. The fix cycles the token.
 */

use App\Livewire\Storefront\Account\Profile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => test()->seed(RoleSeeder::class));

function seedSession(User $user, string $id, string $agent): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.9',
        'user_agent' => $agent,
        'payload' => 'x',
        'last_activity' => now()->timestamp,
    ]);
}

test('log out other devices deletes the other session AND cycles the remember token', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $user->setRememberToken('phone-remember-token');
    $user->save();

    seedSession($user, 'phone-session', 'phone');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('logout_others_password', 'password')
        ->call('logoutOtherDevices')
        ->assertHasNoErrors();

    // The phone's session row is gone...
    expect(DB::table('sessions')->where('id', 'phone-session')->exists())->toBeFalse()
        // ...and the remember token was cycled, so the phone's remember cookie
        // (built on the old token) can no longer re-authenticate it.
        ->and($user->fresh()->getRememberToken())->not->toBe('phone-remember-token');
});

test('the old remember token no longer resolves a user after the logout', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $user->setRememberToken('phone-remember-token');
    $user->save();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('logout_others_password', 'password')
        ->call('logoutOtherDevices')
        ->assertHasNoErrors();

    // This is exactly what the recaller does on the phone's next request:
    // look up (id, old-token). It must now come back empty → phone stays out.
    $byOldToken = Auth::guard()->getProvider()->retrieveByToken($user->id, 'phone-remember-token');

    expect($byOldToken)->toBeNull();
});

test('the device that ran the logout keeps its own session', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('logout_others_password', 'password')
        ->call('logoutOtherDevices')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue();
});
