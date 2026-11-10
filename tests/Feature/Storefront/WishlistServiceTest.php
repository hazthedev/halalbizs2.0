<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\WishlistService;

test('toggle() saves then unsaves and reports the new state', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $service = app(WishlistService::class);

    expect($service->toggle($user, $product))->toBeTrue()
        ->and(Wishlist::where('user_id', $user->id)->count())->toBe(1)
        ->and($service->ids($user))->toBe([$product->id])
        ->and($service->toggle($user, $product))->toBeFalse()
        ->and(Wishlist::count())->toBe(0)
        ->and($service->ids($user))->toBe([]);
});

test('products() lists the wishlist most recently saved first', function () {
    $user = User::factory()->create();
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    Wishlist::forceCreate(['user_id' => $user->id, 'product_id' => $first->id, 'created_at' => now()->subMinute()]);
    Wishlist::create(['user_id' => $user->id, 'product_id' => $second->id]);

    expect(app(WishlistService::class)->products($user)->pluck('id')->all())->toBe([$second->id, $first->id]);
});
