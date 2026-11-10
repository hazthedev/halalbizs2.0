<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\URL;

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
