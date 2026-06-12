<?php

use App\Livewire\Storefront\Account\Addresses;
use App\Livewire\Storefront\Account\Notifications;
use App\Livewire\Storefront\Account\Profile;
use App\Livewire\Storefront\Account\WishlistPage;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('guests are redirected to login from the account area', function () {
    $this->get('/account')->assertRedirect(route('login'));
});

test('account pages render for authenticated users', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk();
})->with(['account.profile', 'account.addresses', 'account.wishlist', 'account.notifications']);

test('profile update persists', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'Nur Aisyah')
        ->set('phone', '013-987 6543')
        ->set('preferred_locale', 'ms')
        ->set('preferred_currency', 'USD')
        ->call('updateProfile')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $user->refresh();

    expect($user->name)->toBe('Nur Aisyah')
        ->and($user->phone)->toBe('013-987 6543')
        ->and($user->preferred_locale)->toBe('ms')
        ->and($user->preferred_currency)->toBe('USD');
});

test('password change requires the current password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('first created address becomes the default', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Addresses::class)
        ->call('create')
        ->set('recipient_name', 'Aisha binti Ali')
        ->set('phone', '012-345 6789')
        ->set('line1', '12, Jalan Ampang')
        ->set('postcode', '50450')
        ->set('city', 'Kuala Lumpur')
        ->set('state', 'Kuala Lumpur')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->addresses()->count())->toBe(1)
        ->and($user->addresses()->first()->is_default)->toBeTrue()
        ->and($user->addresses()->first()->country)->toBe('MY');
});

test('setting a default address unsets the previous default', function () {
    $user = User::factory()->create();
    $first = Address::factory()->default()->create(['user_id' => $user->id]);
    $second = Address::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Addresses::class)
        ->call('setDefault', $second->id);

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse();
});

test('the default address cannot be deleted while others exist', function () {
    $user = User::factory()->create();
    $default = Address::factory()->default()->create(['user_id' => $user->id]);
    Address::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Addresses::class)
        ->call('delete', $default->id)
        ->assertDispatched('toast', type: 'error');

    expect(Address::find($default->id))->not->toBeNull();
});

test('wishlist page shows wishlisted products', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => ['en' => 'Sambal Nyet Berapi', 'ms' => 'Sambal Nyet Berapi'],
    ]);

    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    Livewire::actingAs($user)
        ->test(WishlistPage::class)
        ->assertSee('Sambal Nyet Berapi');
});

test('notifications can be marked as read', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\OrderShipped',
        'data' => ['message' => 'Your order has shipped'],
    ]);

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->assertSee('Your order has shipped')
        ->call('markAllRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});
