<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;

test('setSelected() and setAllSelected() tick lines for one variant, one store or the whole cart', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create()->variants()->first();
    $b = Product::factory()->create()->variants()->first();
    $service = app(CartService::class);

    $service->addItem($user, $a, 1);
    $service->addItem($user, $b, 1);

    $selected = fn () => CartItem::whereIn('product_variant_id', [$a->id, $b->id])->orderBy('product_variant_id')->pluck('selected')->all();

    expect($selected())->toBe([true, true]);

    $service->setSelected($user, $a, false);
    expect($selected())->toBe([false, true]);

    $service->setAllSelected($user, false);
    expect($selected())->toBe([false, false]);

    $service->setAllSelected($user, true, $b->product->store_id);
    expect($selected())->toBe([false, true]);

    $service->setAllSelected($user, true);
    expect($selected())->toBe([true, true]);
});
