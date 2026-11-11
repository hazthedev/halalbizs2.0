<?php

use App\Http\Controllers\AppWebSignInController;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function webLinkBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

/** Issue a link as $user through the API, then drop the bearer guard so the web visit starts signed out. */
function webLinkFor(User $user): string
{
    $url = test()->withToken($user->createToken('pest-phone')->plainTextToken)
        ->postJson('/api/v1/me/web-sign-in-link', ['to' => 'seller-apply'])
        ->assertOk()
        ->json('data.url');

    app('auth')->forgetGuards();
    test()->withoutToken();

    return $url;
}

test('the link signs the token user into the web once and lands on seller sign-up', function () {
    $user = webLinkBuyer();
    $url = webLinkFor($user);

    expect($url)->toStartWith(url('/app/sign-in'))->toContain('signature=');

    $this->get($url)->assertRedirect(route('seller.apply'));
    $this->assertAuthenticatedAs($user, 'web');

    auth('web')->logout();
    $this->get($url)->assertForbidden();
    $this->assertGuest('web');
});

test('the link expires after five minutes', function () {
    $url = webLinkFor(webLinkBuyer());

    $this->travel(5)->minutes();
    $this->travel(1)->seconds();

    $this->get($url)->assertForbidden();
    $this->assertGuest('web');
});

test('a link issued for one user cannot sign in another', function () {
    $owner = webLinkBuyer();
    $other = webLinkBuyer();
    parse_str((string) parse_url(webLinkFor($owner), PHP_URL_QUERY), $query);

    $forged = URL::temporarySignedRoute('app.web-sign-in', now()->addMinutes(5), ['user' => $other->id, 'nonce' => $query['nonce'], 'to' => 'seller-apply']);

    $this->get($forged)->assertForbidden();
    $this->assertGuest('web');
});

test('the link needs a signed-in app user and a known destination', function () {
    $this->postJson('/api/v1/me/web-sign-in-link', ['to' => 'seller-apply'])->assertUnauthorized();

    $user = webLinkBuyer();
    $this->withToken($user->createToken('pest-phone')->plainTextToken)
        ->postJson('/api/v1/me/web-sign-in-link', ['to' => 'admin'])
        ->assertUnprocessable();
});

test('admin accounts never get a web sign-in link, nor can one issued earlier be used', function () {
    $admin = webLinkBuyer();
    $admin->assignRole('admin');
    $superadmin = webLinkBuyer();
    $superadmin->forceFill(['is_superadmin' => true])->save();

    foreach ([$admin, $superadmin] as $user) {
        $this->withToken($user->createToken('pest-phone')->plainTextToken)
            ->postJson('/api/v1/me/web-sign-in-link', ['to' => 'seller-apply'])
            ->assertForbidden();
        app('auth')->forgetGuards();
    }
    $this->withoutToken();

    // A link issued while the user was a buyer stops working once they are an admin.
    $promoted = webLinkBuyer();
    $url = webLinkFor($promoted);
    $promoted->assignRole('admin');

    $this->get($url)->assertForbidden();
    $this->assertGuest('web');
});

test('every allowlisted destination is a real named route and nothing else is accepted', function () {
    foreach (AppWebSignInController::DESTINATIONS as $route) {
        expect(Route::has($route))->toBeTrue($route);
    }

    $user = webLinkBuyer();
    $token = $user->createToken('pest-phone')->plainTextToken;
    foreach (['https://evil.test', 'orders.invoice'] /* a route name is not a destination; mind the 5/min throttle */ as $to) {
        $this->withToken($token)->postJson('/api/v1/me/web-sign-in-link', ['to' => $to])->assertUnprocessable();
    }
    foreach (['seller-certificates' => 'seller.certificates', 'seller-vouchers' => 'seller.vouchers.index', 'support' => 'help.tickets'] as $to => $route) {
        $url = $this->withToken($token)->postJson('/api/v1/me/web-sign-in-link', ['to' => $to])->assertOk()->json('data.url');
        app('auth')->forgetGuards();
        $this->withoutToken();
        $this->get($url)->assertRedirect(route($route));
        auth('web')->logout();
        $this->withToken($token);
    }
});

test('an invoice link opens the buyer\'s own sub-order invoice once, and never another user\'s', function () {
    $owner = webLinkBuyer();
    $other = webLinkBuyer();
    $mine = SubOrder::factory()->create(['order_id' => Order::factory()->create(['user_id' => $owner->id])->id]);
    $theirs = SubOrder::factory()->create(['order_id' => Order::factory()->create(['user_id' => $other->id])->id]);
    $token = $owner->createToken('pest-phone')->plainTextToken;

    // Needs an id, and refuses someone else's order (404, as the rest of the API).
    $this->withToken($token)->postJson('/api/v1/me/web-sign-in-link', ['to' => 'invoice'])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/v1/me/web-sign-in-link', ['to' => 'invoice', 'id' => $theirs->id])->assertNotFound();

    $url = $this->withToken($token)->postJson('/api/v1/me/web-sign-in-link', ['to' => 'invoice', 'id' => $mine->id])->assertOk()->json('data.url');
    app('auth')->forgetGuards();
    $this->withoutToken();

    $this->get($url)->assertRedirect(route('account.orders.invoice', $mine));
    $this->assertAuthenticatedAs($owner, 'web');

    // Single use.
    auth('web')->logout();
    $this->get($url)->assertForbidden();
    $this->assertGuest('web');

    // A signed link forged for another user's sub-order is refused at the door too.
    $nonce = Str::random(40);
    Cache::put('app-web-sign-in:'.$nonce, $owner->id, now()->addMinutes(5));
    $forged = URL::temporarySignedRoute('app.web-sign-in', now()->addMinutes(5), ['user' => $owner->id, 'nonce' => $nonce, 'to' => 'invoice', 'id' => $theirs->id]);
    $this->get($forged)->assertForbidden();
    $this->assertGuest('web');
});
