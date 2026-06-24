<?php

use App\Enums\CoinTransactionType;
use App\Livewire\Storefront\Checkout;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CoinService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.redemption_rate_sen' => 1, 'coins.max_redemption_sen' => 5000]);
});

test('the checkout coin toggle previews the discount and updates the grand total', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    app(CoinService::class)->credit($buyer, 8000, CoinTransactionType::Earn); // > 5000 cap

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee(__('Use my coins'))
        ->assertSee('RM 100.00')        // grand total before coins
        ->set('useCoins', true)
        ->assertSee('-RM 50.00')        // 5000-coin cap → RM50 off
        ->assertSee('RM 50.00');        // grand total after coins
});

test('the coin toggle is hidden when the buyer has no coins', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertDontSee(__('Use my coins'));
});
