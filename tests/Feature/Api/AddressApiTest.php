<?php

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function apiAddressPayload(array $overrides = []): array
{
    return $overrides + [
        'recipient_name' => 'Aisha binti Ali',
        'phone' => '0123456789',
        'line1' => '12 Jalan Damai',
        'postcode' => '50450',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
    ];
}

function apiAddressUser(): array
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    app('auth')->forgetGuards();

    return [$user, test()->withToken($user->createToken('pest-phone')->plainTextToken)];
}

test('addresses CRUD: the first address is the default, updates and deletes work', function () {
    [$user, $http] = apiAddressUser();

    $http->getJson('/api/v1/addresses')->assertOk()->assertJsonPath('data', []);

    $first = $http->postJson('/api/v1/addresses', apiAddressPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.country', 'MY')
        ->assertJsonPath('data.label', null)
        ->json('data.id');

    $second = $http->postJson('/api/v1/addresses', apiAddressPayload(['label' => 'Office', 'state' => 'Selangor']))
        ->assertCreated()
        ->assertJsonPath('data.is_default', false)
        ->json('data.id');

    $http->patchJson("/api/v1/addresses/{$second}", apiAddressPayload(['state' => 'Selangor', 'is_default' => true]))
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect(Address::find($first)->is_default)->toBeFalse();

    // Unflagging the current default is ignored: the book always keeps one.
    $http->patchJson("/api/v1/addresses/{$second}", apiAddressPayload(['state' => 'Selangor', 'is_default' => false]))
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    // The default cannot go while another address exists.
    $http->deleteJson("/api/v1/addresses/{$second}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'address_is_default');

    $http->deleteJson("/api/v1/addresses/{$first}")->assertOk();

    expect($user->addresses()->count())->toBe(1);
});

test('address validation mirrors the web form', function () {
    [, $http] = apiAddressUser();

    $http->postJson('/api/v1/addresses', apiAddressPayload(['postcode' => '1234', 'state' => 'Nowhere']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.fields.postcode.0', __('Postcode must be exactly 5 digits, like 50450.'))
        ->assertJsonStructure(['error' => ['fields' => ['state']]]);
});

test("another user's address is a 404", function () {
    [, $http] = apiAddressUser();
    $other = Address::factory()->create(['user_id' => User::factory()->create()->id]);

    $http->patchJson("/api/v1/addresses/{$other->id}", apiAddressPayload())
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $http->deleteJson("/api/v1/addresses/{$other->id}")->assertNotFound();

    expect($other->fresh())->not->toBeNull();
});
