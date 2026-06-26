<?php

use App\Enums\PaymentMethod;
use App\Livewire\Storefront\RelatedProducts;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('"Add all to cart" adds the frequently-bought-together products', function () {
    $buyer = User::factory()->create();
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $a = Product::factory()->create(['cod_enabled' => true]);
    $b = Product::factory()->for($a->store)->create(['cod_enabled' => true]); // same store → one order
    $a->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    $b->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);

    // Co-purchase A + B in one order so FBT links them.
    app(CartService::class)->addItem($buyer, $a->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $b->variants->first(), 1);
    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    // The cart is empty after checkout; "Add all" from A's FBT strip pulls B back in.
    Livewire::actingAs($buyer)
        ->test(RelatedProducts::class, ['product' => $a])
        ->call('addAllBoughtTogether');

    $cartVariantIds = Cart::where('user_id', $buyer->id)->first()?->items->pluck('product_variant_id')->all() ?? [];

    expect($cartVariantIds)->toContain($b->variants->first()->id);
});
