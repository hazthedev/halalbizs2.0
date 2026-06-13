<?php

use App\Enums\ProductStatus;
use App\Livewire\Seller\Dashboard;
use App\Livewire\Seller\Settings;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function makeApprovedSeller(): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

// ===== Dashboard =====

test('an approved seller sees the dashboard with stat cards', function () {
    $this->actingAs(makeApprovedSeller())
        ->get('/seller')
        ->assertOk()
        ->assertSee(__("Today's orders"))
        ->assertSee(__('To ship'))
        ->assertSee(__('Live products'))
        ->assertSee(__('Low stock'))
        ->assertSee(__('Recent orders'))
        ->assertSee(__('Revenue over time')); // replaced the placeholder "Sales (14 days)" sparkline
});

test('a pending seller is redirected away from the dashboard', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]); // pending

    $this->actingAs($user)->get('/seller')->assertRedirect(route('seller.status'));
});

test('dashboard counts are store-scoped and never leak another seller\'s rows', function () {
    $sellerA = makeApprovedSeller();
    $sellerB = makeApprovedSeller();

    // Seller A: 2 live products (healthy stock) + 1 delisted.
    Product::factory()->count(2)->create(['store_id' => $sellerA->store->id, 'status' => ProductStatus::Live])
        ->each(fn (Product $product) => $product->variants()->update(['stock' => 50]));
    Product::factory()->create(['store_id' => $sellerA->store->id, 'status' => ProductStatus::Delisted]);

    // Seller B: 3 live products, all low on stock.
    Product::factory()->count(3)->create(['store_id' => $sellerB->store->id, 'status' => ProductStatus::Live])
        ->each(fn (Product $product) => $product->variants()->update(['stock' => 2]));

    Livewire::actingAs($sellerA)
        ->test(Dashboard::class)
        ->assertSet('liveProducts', 2)
        ->assertSet('lowStock', 0)
        ->assertSet('todayOrders', 0)
        ->assertSet('toShip', 0);

    Livewire::actingAs($sellerB)
        ->test(Dashboard::class)
        ->assertSet('liveProducts', 3)
        ->assertSet('lowStock', 3);
});

// ===== Settings =====

test('an approved seller sees the settings page', function () {
    $this->actingAs(makeApprovedSeller())
        ->get('/seller/settings')
        ->assertOk()
        ->assertSee(__('Shop settings'))
        ->assertSee(__('Holiday mode'))
        ->assertSee(__('Shipping'))
        ->assertSee(__('Bank details'));
});

test('settings saves a flat shipping fee in sen from an RM input', function () {
    $seller = makeApprovedSeller();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('shippingMode', 'flat')
        ->set('flatFee', '12.50')
        ->set('freeOver', '100')
        ->call('saveShipping')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $store = $seller->store->fresh();

    expect($store->shipping_mode)->toBe('flat')
        ->and($store->shipping_flat_fee_sen)->toBe(1250)
        ->and($store->free_shipping_over_sen)->toBe(10000);
});

test('an unparseable flat fee is rejected', function () {
    Livewire::actingAs(makeApprovedSeller())
        ->test(Settings::class)
        ->set('shippingMode', 'flat')
        ->set('flatFee', 'abc')
        ->call('saveShipping')
        ->assertHasErrors(['flatFee']);
});

test('settings saves the state shipping matrix in sen', function () {
    $seller = makeApprovedSeller();

    $component = Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('shippingMode', 'matrix');

    foreach (range(0, 15) as $index) {
        $component->set("matrix.$index", $index === 0 ? '7.00' : '15.90');
    }

    $component->call('saveShipping')->assertHasNoErrors();

    $store = $seller->store->fresh();

    expect($store->shipping_mode)->toBe('matrix')
        ->and($store->shipping_matrix['Johor'])->toBe(700)
        ->and($store->shipping_matrix['Sabah'])->toBe(1590)
        ->and($store->shipping_matrix)->toHaveCount(16);
});

test('a matrix with a missing state fee is rejected', function () {
    $component = Livewire::actingAs(makeApprovedSeller())
        ->test(Settings::class)
        ->set('shippingMode', 'matrix');

    foreach (range(0, 14) as $index) {
        $component->set("matrix.$index", '8.00');
    }

    $component->set('matrix.15', '')
        ->call('saveShipping')
        ->assertHasErrors(['matrix.15']);
});

test('apply to all fills every state fee', function () {
    Livewire::actingAs(makeApprovedSeller())
        ->test(Settings::class)
        ->set('shippingMode', 'matrix')
        ->set('applyAll', '9.90')
        ->call('applyToAll')
        ->assertHasNoErrors()
        ->assertSet('matrix.0', '9.90')
        ->assertSet('matrix.15', '9.90');
});

test('the holiday mode toggle persists immediately', function () {
    $seller = makeApprovedSeller();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('holidayMode', true)
        ->assertDispatched('toast');

    expect($seller->store->fresh()->holiday_mode)->toBeTrue();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('holidayMode', false);

    expect($seller->store->fresh()->holiday_mode)->toBeFalse();
});

test('bank details update is saved to the bank_details json', function () {
    $seller = makeApprovedSeller();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('bankName', 'Hong Leong')
        ->set('accountName', 'Kedai Pak Ali Enterprise')
        ->set('accountNumber', '12345678901234')
        ->call('saveBank')
        ->assertHasNoErrors();

    expect($seller->store->fresh()->bank_details)->toBe([
        'bank_name' => 'Hong Leong',
        'account_name' => 'Kedai Pak Ali Enterprise',
        'account_number' => '12345678901234',
    ]);
});

test('profile saves description and state', function () {
    $seller = makeApprovedSeller();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('description', 'Fresh kuih daily, delivered across the Klang Valley.')
        ->set('state', 'Kuala Lumpur')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $store = $seller->store->fresh();

    expect($store->description)->toBe('Fresh kuih daily, delivered across the Klang Valley.')
        ->and($store->state)->toBe('Kuala Lumpur');
});

test('the shop name is not editable from settings', function () {
    $seller = makeApprovedSeller();
    $originalName = $seller->store->name;

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->assertSee($originalName)
        ->assertSee(__('Contact support to rename your shop.'));

    expect($seller->store->fresh()->name)->toBe($originalName);
});
