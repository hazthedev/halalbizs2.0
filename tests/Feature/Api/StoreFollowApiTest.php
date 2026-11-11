<?php

use App\Models\Store;
use App\Models\StoreFollow;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function followBuyer(): array
{
    $user = User::factory()->create();
    $user->assignRole('buyer');
    app('auth')->forgetGuards();

    return [$user, test()->withToken($user->createToken('pest')->plainTextToken)];
}

test('follow and unfollow are idempotent, counted, and one row per pair', function () {
    [$user, $http] = followBuyer();
    $store = Store::factory()->approved()->create();

    $http->postJson("/api/v1/stores/{$store->slug}/follow")->assertOk()
        ->assertJsonPath('data.is_following', true)->assertJsonPath('data.followers_count', 1);
    $http->postJson("/api/v1/stores/{$store->slug}/follow")->assertOk()->assertJsonPath('data.followers_count', 1);
    expect(StoreFollow::where('user_id', $user->id)->where('store_id', $store->id)->count())->toBe(1);

    $http->deleteJson("/api/v1/stores/{$store->slug}/follow")->assertOk()
        ->assertJsonPath('data.is_following', false)->assertJsonPath('data.followers_count', 0);
    $http->deleteJson("/api/v1/stores/{$store->slug}/follow")->assertOk()->assertJsonPath('data.followers_count', 0);
});

test('the database refuses a duplicate (user, store) row', function () {
    $user = User::factory()->create();
    $store = Store::factory()->approved()->create();
    StoreFollow::create(['user_id' => $user->id, 'store_id' => $store->id]);

    expect(fn () => StoreFollow::create(['user_id' => $user->id, 'store_id' => $store->id]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('a seller cannot follow their own store', function () {
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);
    app('auth')->forgetGuards();

    $this->withToken($owner->createToken('pest')->plainTextToken)
        ->postJson("/api/v1/stores/{$store->slug}/follow")
        ->assertForbidden()->assertJsonPath('error.code', 'own_store');
    expect(StoreFollow::count())->toBe(0);
});

test('a pending store cannot be followed', function () {
    [, $http] = followBuyer();
    $store = Store::factory()->create(); // not approved

    $http->postJson("/api/v1/stores/{$store->slug}/follow")->assertNotFound();
});

test('guests get 401 on follow, unfollow and the followed list', function () {
    $store = Store::factory()->approved()->create();

    $this->postJson("/api/v1/stores/{$store->slug}/follow")->assertUnauthorized();
    $this->deleteJson("/api/v1/stores/{$store->slug}/follow")->assertUnauthorized();
    $this->getJson('/api/v1/me/followed-stores')->assertUnauthorized();
});

test('store show carries followers_count publicly and is_following for the caller only', function () {
    [$user, $http] = followBuyer();
    $store = Store::factory()->approved()->create();
    StoreFollow::create(['user_id' => User::factory()->create()->id, 'store_id' => $store->id]);

    $this->getJson("/api/v1/stores/{$store->slug}")->assertOk()
        ->assertJsonPath('data.followers_count', 1)->assertJsonPath('data.is_following', false);

    $http->getJson("/api/v1/stores/{$store->slug}")->assertJsonPath('data.is_following', false);
    $http->postJson("/api/v1/stores/{$store->slug}/follow")->assertOk();
    $http->getJson("/api/v1/stores/{$store->slug}")
        ->assertJsonPath('data.followers_count', 2)->assertJsonPath('data.is_following', true);
});

test('followed-stores lists only the caller\'s follows, newest first, paged', function () {
    [$user, $http] = followBuyer();
    $other = User::factory()->create();
    $a = Store::factory()->approved()->create();
    $b = Store::factory()->approved()->create();
    $c = Store::factory()->approved()->create();
    StoreFollow::create(['user_id' => $user->id, 'store_id' => $a->id]);
    StoreFollow::create(['user_id' => $user->id, 'store_id' => $b->id]);
    StoreFollow::create(['user_id' => $other->id, 'store_id' => $c->id]);

    $res = $http->getJson('/api/v1/me/followed-stores')->assertOk()->assertJsonPath('meta.total', 2);
    expect($res->json('data.*.id'))->toBe([$b->id, $a->id])->not->toContain($c->id);
    $res->assertJsonStructure(['data' => [['id', 'slug', 'name', 'logo', 'followers_count']]]);
});
