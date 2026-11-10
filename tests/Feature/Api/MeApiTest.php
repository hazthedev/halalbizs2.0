<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Profile;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function apiBuyer(): User
{
    $user = User::factory()->create(['phone' => '0123456789', 'phone_verified_at' => now()]);
    $user->assignRole('buyer');

    return $user;
}

/**
 * A fresh bearer request. One Pest test is one app instance, so the sanctum
 * guard would otherwise keep the first request's user for the next one.
 */
function apiAs(User $user)
{
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('pest-phone')->plainTextToken);
}

test('GET me returns the profile shape with roles and store', function () {
    $user = apiBuyer();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    apiAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.roles', ['buyer', 'seller'])
        ->assertJsonPath('data.store.id', $store->id)
        ->assertJsonPath('data.store.slug', $store->slug)
        ->assertJsonPath('data.store.status', 'approved')
        ->assertJsonPath('data.unread_notifications', 0)
        ->assertJsonPath('data.coins', 0);

    apiAs(apiBuyer())->getJson('/api/v1/me')->assertJsonPath('data.store', null);
});

test('GET me without a token is a 401 envelope', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('PATCH me updates name, phone and locale; a new phone resets its verification', function () {
    $user = apiBuyer();

    apiAs($user)->patchJson('/api/v1/me', ['name' => 'Nur Aisyah', 'phone' => '0199999999', 'preferred_locale' => 'ms'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nur Aisyah')
        ->assertJsonPath('data.phone', '0199999999')
        ->assertJsonPath('data.preferred_locale', 'ms');

    expect($user->fresh()->phone_verified_at)->toBeNull();

    apiAs($user)->patchJson('/api/v1/me', ['preferred_locale' => 'xx'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['preferred_locale']]]);
});

test('PUT me/password needs the current password and the password rules', function () {
    Http::fake();
    $user = apiBuyer();

    apiAs($user)->putJson('/api/v1/me/password', [
        'current_password' => 'wrong',
        'password' => 'new-long-password',
        'password_confirmation' => 'new-long-password',
    ])->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['current_password']]]);

    $user->createToken('tablet');

    $old = $user->createToken('pest-phone')->plainTextToken;
    app('auth')->forgetGuards();

    $fresh = test()->withToken($old)->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'new-long-password',
        'password_confirmation' => 'new-long-password',
    ])->assertOk()->assertJsonStructure(['data' => ['token']])->json('data.token');

    expect(Hash::check('new-long-password', $user->fresh()->password))->toBeTrue()
        // Every token dies, this one included (it may have been copied); the
        // device that changed the password continues on the fresh one.
        ->and($user->tokens()->pluck('name')->all())->toBe(['pest-phone']);

    app('auth')->forgetGuards();
    test()->withToken($old)->getJson('/api/v1/me')->assertStatus(401);
    app('auth')->forgetGuards();
    test()->withToken($fresh)->getJson('/api/v1/me')->assertOk();
});

test('a bearer token older than the expiry window is rejected', function () {
    $user = apiBuyer();
    $token = $user->createToken('pest-phone');
    $token->accessToken->forceFill(['created_at' => now()->subMinutes(config('sanctum.expiration') + 1)])->save();

    test()->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertStatus(401);
});

test('PUT me/password allows five guesses a minute per user', function () {
    $user = apiBuyer();
    $http = apiAs($user);

    foreach (range(1, 5) as $attempt) {
        $http->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'x'])->assertStatus(422);
    }

    $http->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'x'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'throttled');
});

test('DELETE me anonymises, soft-deletes and revokes every token', function () {
    $user = apiBuyer();
    $user->createToken('tablet');
    $user->deviceTokens()->create(['token' => 'fcm-1', 'platform' => 'ios']);

    apiAs($user)->deleteJson('/api/v1/me', ['password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['password']]]);

    apiAs($user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertOk();

    $fresh = User::withTrashed()->find($user->id);

    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->name)->toBe('Deleted user')
        ->and($fresh->email)->toBe('deleted-'.$user->id.'@anonymized.local')
        ->and($fresh->phone)->toBeNull()
        ->and($fresh->tokens()->count())->toBe(0)
        ->and($fresh->deviceTokens()->count())->toBe(0);
});

test('DELETE me is blocked while orders are in progress, and the web profile still blocks too', function () {
    $user = apiBuyer();
    $order = Order::factory()->create(['user_id' => $user->id]);
    SubOrder::factory()->status(SubOrderStatus::Processing)->create(['order_id' => $order->id]);

    apiAs($user)->deleteJson('/api/v1/me', ['password' => 'password'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'account_deletion_blocked');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertDispatched('toast', type: 'error');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeFalse();
});
