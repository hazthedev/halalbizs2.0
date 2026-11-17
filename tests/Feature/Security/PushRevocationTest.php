<?php

use App\Livewire\Admin\Buyers\Detail;
use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

/*
 * Push registrations belong to app sign-ins: wherever API tokens are revoked,
 * the device tokens go too, or a revoked phone keeps receiving the account's
 * order and chat notifications.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

function pushedUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $user->createToken('old-phone');
    $user->deviceTokens()->create(['token' => 'old-phone-fcm', 'platform' => 'android']);

    return $user;
}

test('app password change drops push registrations; the changing phone keeps a fresh token', function () {
    $user = pushedUser();
    $token = $user->createToken('this-phone')->plainTextToken;
    app('auth')->forgetGuards();

    $this->withToken($token)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'a-new-Passw0rd!x', 'password_confirmation' => 'a-new-Passw0rd!x',
    ])->assertOk();

    expect($user->deviceTokens()->count())->toBe(0)->and($user->tokens()->count())->toBe(1);
});

test('web password change drops push registrations', function () {
    $user = pushedUser();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')->assertHasNoErrors();

    expect($user->deviceTokens()->count())->toBe(0);
});

test('password reset drops push registrations', function () {
    $user = pushedUser();

    Livewire::test(ResetPassword::class, ['token' => Password::createToken($user)])
        ->set('email', $user->email)
        ->set('password', 'dummy-recovery-password')
        ->set('password_confirmation', 'dummy-recovery-password')
        ->call('resetPassword')->assertHasNoErrors();

    expect($user->deviceTokens()->count())->toBe(0);
});

test('admin suspension drops push registrations', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);
    $user = pushedUser();

    Livewire::actingAs($admin)->test(Detail::class, ['user' => $user])
        ->set('suspendReason', 'Chargeback abuse across multiple orders.')
        ->call('suspend')->assertHasNoErrors();

    expect($user->deviceTokens()->count())->toBe(0);
});
