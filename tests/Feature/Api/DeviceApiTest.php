<?php

use App\Models\DeviceToken;
use App\Models\User;

test('POST devices upserts the token and DELETE removes only the owner\'s', function () {
    $user = User::factory()->create();
    $owner = $user->createToken('pest-phone')->plainTextToken;
    $http = $this->withToken($owner);

    $http->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'ios'])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'ios');

    // Same install re-registering: one row, platform refreshed.
    $http->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertCreated();

    expect(DeviceToken::where('token', 'fcm-abc')->count())->toBe(1)
        ->and(DeviceToken::where('token', 'fcm-abc')->value('platform'))->toBe('android');

    $http->postJson('/api/v1/devices', ['token' => 'fcm-x', 'platform' => 'web'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['platform']]]);

    // Another user cannot delete it; the owner can.
    $other = User::factory()->create();
    app('auth')->forgetGuards(); // one app per test: drop the guard's cached user
    $this->withToken($other->createToken('p')->plainTextToken)->deleteJson('/api/v1/devices/fcm-abc')->assertOk();
    expect(DeviceToken::where('token', 'fcm-abc')->exists())->toBeTrue();

    app('auth')->forgetGuards();
    $this->withToken($owner)->deleteJson('/api/v1/devices/fcm-abc')->assertOk();
    expect(DeviceToken::where('token', 'fcm-abc')->exists())->toBeFalse();
});
