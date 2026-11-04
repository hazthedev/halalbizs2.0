<?php

/**
 * Role-aware post-login landing (App\Support\PostLoginRedirect).
 *
 * The bug this pins down: redirect()->guest() stashes ANY bounced URL as
 * url.intended — including sections the eventual user can never enter. A guest
 * touched /seller, then logged in as the platform admin, and the stale
 * intended URL walked them through EnsureSeller's no-store branch onto the
 * "Become a seller" application form.
 */

use App\Livewire\Storefront\Auth\Login;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(fn () => test()->seed(RoleSeeder::class));

function landingBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

function landingAdmin(): User
{
    // No 2FA on purpose: Login redirects directly, which is the path under
    // test. (The TwoFactorChallenge redirect shares the same helper.)
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function landingSeller(bool $approved = true): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $user->assignRole('seller');

    $factory = Store::factory();
    if ($approved) {
        $factory = $factory->approved();
    }
    $factory->create(['user_id' => $user->id]); // default state = pending

    return $user;
}

function loginAs(User $user): Testable
{
    return Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');
}

// ── The reproduced bug ─────────────────────────────────────────────────────

test('a stale /seller intended URL does not walk an admin into the seller application', function () {
    // Exactly what EnsureSeller's guest bounce leaves behind.
    session()->put('url.intended', url('/seller'));

    loginAs(landingAdmin())->assertRedirect(route('admin.dashboard'));
});

test('a stale /seller intended URL sends a plain buyer home, not to the application', function () {
    session()->put('url.intended', url('/seller'));

    loginAs(landingBuyer())->assertRedirect(route('home'));
});

// ── Legitimate intended URLs still win ─────────────────────────────────────

test('an approved seller bounced off the seller centre returns to it', function () {
    session()->put('url.intended', url('/seller/orders'));

    loginAs(landingSeller())->assertRedirect(url('/seller/orders'));
});

test('a buyer who deliberately clicked Become a seller returns to the application', function () {
    // /seller/apply is auth-only by design — the one seller-prefixed URL any
    // authenticated user may hold as intended.
    session()->put('url.intended', url('/seller/apply'));

    loginAs(landingBuyer())->assertRedirect(url('/seller/apply'));
});

test('an ordinary storefront intended URL is honoured for everyone', function () {
    session()->put('url.intended', url('/account/orders'));

    loginAs(landingBuyer())->assertRedirect(url('/account/orders'));
});

test('an /admin intended URL is honoured for an admin but not for a buyer', function () {
    session()->put('url.intended', url('/admin/catalog/moderation'));
    loginAs(landingAdmin())->assertRedirect(url('/admin/catalog/moderation'));

    session()->put('url.intended', url('/admin/catalog/moderation'));
    loginAs(landingBuyer())->assertRedirect(route('home'));
});

// ── Natural homes when nothing was intended ────────────────────────────────

test('with no intended URL each role lands at its own home', function () {
    loginAs(landingAdmin())->assertRedirect(route('admin.dashboard'));
    loginAs(landingSeller())->assertRedirect(route('home'));
    loginAs(landingBuyer())->assertRedirect(route('home'));
});

test('a pending seller lands on the storefront, not a seller centre they cannot enter', function () {
    loginAs(landingSeller(approved: false))->assertRedirect(route('home'));
});

// ── Guest-only pages are never a landing (HBIZ-B2) ─────────────────────────

test('a stale guest-page intended URL lands on home, not back on the auth page', function (string $path) {
    session()->put('url.intended', url($path));
    loginAs(landingBuyer())->assertRedirect(route('home'));

    // The seller/admin prefix rules would otherwise accept their own entrances.
    session()->put('url.intended', url($path));
    loginAs(landingSeller())->assertRedirect(route('home'));
})->with([
    '/login', '/register', '/forgot-password', '/reset-password/abc123',
    '/two-factor-challenge', '/seller/login', '/seller/register', '/login?foo=1',
]);

test('a stale /admin/login intended URL sends an admin to the admin home', function () {
    session()->put('url.intended', url('/admin/login'));

    loginAs(landingAdmin())->assertRedirect(route('admin.dashboard'));
});

test('a path that merely starts like an auth page is still honoured', function () {
    session()->put('url.intended', url('/login-help'));

    loginAs(landingBuyer())->assertRedirect(url('/login-help'));
});

test('a cart intended URL is honoured', function () {
    session()->put('url.intended', url('/cart'));

    loginAs(landingBuyer())->assertRedirect(url('/cart'));
});

test('an external intended URL is rejected', function () {
    session()->put('url.intended', 'https://evil.example/cart');

    loginAs(landingBuyer())->assertRedirect(route('home'));
});
