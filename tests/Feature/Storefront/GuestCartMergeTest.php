<?php

use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Session;

// Independent review 2026-11-07: CartService::addItem() refuses sold-out and
// withdrawn variants (BuyerActionException), and the login path merges the
// guest cart through addItem() — a stale guest line must not break sign-in.
test('signing in merges the live guest lines and drops the stale ones', function () {
    $live = Product::factory()->create()->variants->first();
    $soldOut = Product::factory()->create()->variants->first();
    $soldOut->update(['stock' => 0]);

    Session::put('guest_cart', [$live->id => 2, $soldOut->id => 1]);

    $user = User::factory()->create();

    app(CartService::class)->mergeSessionCart($user);

    expect($user->cart->items()->pluck('qty', 'product_variant_id')->all())->toBe([$live->id => 2])
        ->and(Session::get('guest_cart'))->toBeNull();
});
