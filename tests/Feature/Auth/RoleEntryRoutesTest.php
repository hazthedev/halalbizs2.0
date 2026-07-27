<?php

/**
 * Branded entrances: /seller/login, /seller/register, /admin/login.
 *
 * Same shared components, reframed by App\Enums\AuthContext. The context only
 * changes copy + the no-intended-URL landing; it grants no privilege, so a
 * buyer who opens /seller/login is still routed as a buyer.
 */

use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\Register;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(fn () => test()->seed(RoleSeeder::class));

// ── The pages render with the right framing ────────────────────────────────

test('the branded login pages render', function (string $route) {
    test()->get(route($route))->assertOk();
})->with(['login', 'seller.login', 'admin.login', 'seller.register']);

test('the seller login page is framed for sellers, not shoppers', function () {
    test()->get(route('seller.login'))
        ->assertSee('Seller Centre')
        ->assertSee('Open a shop')
        ->assertDontSee('your cart is right where you left it');
});

test('the admin login page says access is granted, never self-served', function () {
    test()->get(route('admin.login'))
        ->assertSee('Authorised staff only')
        ->assertSee('granted by an existing administrator');
});

test('there is no admin self-registration route', function () {
    expect(Route::has('admin.register'))->toBeFalse();
});

// ── Context lands the RIGHT user at the right place ────────────────────────

function entryUser(string $role, bool $approvedStore = false): User
{
    $user = User::factory()->create();
    $user->assignRole($role === 'seller' ? 'buyer' : $role);

    if ($role === 'seller') {
        $user->assignRole('seller');
        $factory = Store::factory();
        if ($approvedStore) {
            $factory = $factory->approved();
        }
        $factory->create(['user_id' => $user->id]);
    }

    return $user;
}

function loginVia(string $context, User $user): Testable
{
    return Livewire::test(Login::class, ['context' => $context])
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');
}

test('an approved seller entering via /seller/login lands in the Seller Centre', function () {
    loginVia('seller', entryUser('seller', approvedStore: true))
        ->assertRedirect(route('seller.dashboard'));
});

test('an admin entering via /admin/login lands in the panel', function () {
    // No 2FA so Login redirects directly — the shared helper is what's tested.
    loginVia('admin', entryUser('admin'))
        ->assertRedirect(route('admin.dashboard'));
});

test('the seller context grants nothing — a plain buyer still lands home', function () {
    loginVia('seller', entryUser('buyer'))->assertRedirect(route('home'));
});

test('the admin context grants nothing — a plain buyer still lands home', function () {
    loginVia('admin', entryUser('buyer'))->assertRedirect(route('home'));
});

test('a seller not yet approved is not dropped into a centre they cannot enter', function () {
    loginVia('seller', entryUser('seller', approvedStore: false))
        ->assertRedirect(route('home'));
});

// ── A genuine intended URL still wins over the context fallback ─────────────

test('an intended storefront URL beats the seller-context fallback', function () {
    session()->put('url.intended', url('/account/orders'));

    loginVia('seller', entryUser('seller', approvedStore: true))
        ->assertRedirect(url('/account/orders'));
});

// ── Seller registration parks the application as the next step ─────────────

test('registering via /seller/register heads to email verification then the application', function () {
    Notification::fake();

    Livewire::test(Register::class, ['context' => 'seller'])
        ->set('name', 'Nadia Seller')
        ->set('email', 'nadia@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    // The application is parked so email verification lands there, not home.
    expect(session('url.intended'))->toBe(route('seller.apply'));
});

test('registering via the plain storefront does not park the seller application', function () {
    Notification::fake();

    Livewire::test(Register::class) // storefront context (default)
        ->set('name', 'Omar Shopper')
        ->set('email', 'omar@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    expect(session('url.intended'))->toBeNull();
});

// ── The guest bounce uses the branded door ─────────────────────────────────

test('a guest hitting the seller centre is bounced to the seller login', function () {
    test()->get(route('seller.dashboard'))->assertRedirect(route('seller.login'));
});

test('a guest hitting the admin panel is bounced to the admin login', function () {
    test()->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
