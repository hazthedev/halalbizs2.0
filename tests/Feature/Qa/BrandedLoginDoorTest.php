<?php

/**
 * QA pass — the branded login doors (/login, /seller/login, /admin/login).
 *
 * Reported by Haze: filling BUYER credentials into the admin login logs you in
 * as the buyer. routes/web.php:62-67 says that is deliberate — same component,
 * `context` reframes copy and landing only, "grants no privilege". These tests
 * check the implementation actually matches that claim, because the dangerous
 * version of this behaviour is not "you get in as a buyer" but "the door, or a
 * stale intended URL, lands a buyer somewhere a buyer cannot go".
 */

use App\Livewire\Storefront\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

// RefreshDatabase rolls the DB back but not spatie's permission cache, so the
// role ids cached by one test can outlive the rows they point at.
afterEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    auth()->logout();
    session()->flush();
});

function doorBuyer(): User
{
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('buyer');

    return $user->fresh();
}

function doorAdmin(): User
{
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'two_factor_method' => null, // no 2FA hop; EnsureAdmin's own guard is covered elsewhere
    ]);
    $user->assignRole('admin');
    $user->forceFill(['is_superadmin' => true])->save();

    return $user->fresh();
}

function signIn(User $user, string $context): string
{
    $component = Livewire::test(Login::class, ['context' => $context])
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    return $component->effects['redirect'] ?? '';
}

it('lets a buyer through the admin door but lands them on the storefront', function () {
    $target = signIn(doorBuyer(), 'admin');

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->hasRole('admin'))->toBeFalse()
        ->and($target)->toBe(route('home'))
        ->and($target)->not->toContain('/admin');
});

it('refuses that buyer at the admin panel itself', function () {
    $buyer = doorBuyer();
    signIn($buyer, 'admin');

    // 403, not a redirect back to the login (which the guest middleware would
    // bounce straight back — that is what a loop looks like).
    test()->actingAs($buyer)->get('/admin')->assertForbidden();
});

it('does not honour a stale admin intended URL for a buyer who came through that door', function () {
    // Guest deep-links into the panel, gets bounced, then signs in as a BUYER.
    test()->get('/admin/orders')->assertRedirect(route('admin.login'));
    expect(session('url.intended'))->toContain('/admin/orders');

    $target = signIn(doorBuyer(), 'admin');

    expect($target)->toBe(route('home'))
        ->and($target)->not->toContain('/admin');
});

it('consumes the stale intended URL so it cannot leak into a later admin login', function () {
    test()->get('/admin/orders');
    signIn(doorBuyer(), 'admin');

    expect(session('url.intended'))->toBeNull();
});

it('does not land a buyer in the seller centre through the seller door', function () {
    $target = signIn(doorBuyer(), 'seller');

    expect($target)->toBe(route('home'))
        ->and($target)->not->toContain('/seller/dashboard');
});

it('still sends a real admin to the panel, through either door', function (string $context) {
    expect(signIn(doorAdmin(), $context))->toBe(route('admin.dashboard'));
})->with(['admin', 'storefront']);

it('honours a legitimate intended URL for someone who may visit it', function () {
    $admin = doorAdmin();

    test()->get('/admin/orders')->assertRedirect(route('admin.login'));

    expect(signIn($admin, 'admin'))->toContain('/admin/orders');
});
