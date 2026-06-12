<?php

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;

test('variant matrix factory builds the full matrix', function () {
    $product = Product::factory()->withVariants(colour: 3, size: 2)->create();

    expect($product->options)->toHaveCount(2)
        ->and($product->options->first()->values)->toHaveCount(3)
        ->and($product->variants)->toHaveCount(6)
        ->and($product->variants->where('is_default', true))->toHaveCount(1);

    $variant = $product->variants->first();
    expect($variant->options_label)->not->toBeNull()
        ->and($variant->option_value_ids)->toHaveCount(2);
});

test('every product has at least one variant — default variant pattern', function () {
    $product = Product::factory()->create();

    expect($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->is_default)->toBeTrue()
        ->and($product->defaultVariant)->not->toBeNull();
});

test('variant resolves by option value ids in PHP', function () {
    $product = Product::factory()->withVariants(colour: 2, size: 2)->create();
    $target = $product->variants->last();

    $resolved = ProductVariant::resolveByValues($product->variants, $target->option_value_ids);

    expect($resolved->id)->toBe($target->id);
});

test('core relationships resolve', function () {
    $product = Product::factory()->create();
    $store = $product->store;

    expect($store)->toBeInstanceOf(Store::class)
        ->and($store->user)->toBeInstanceOf(User::class)
        ->and($store->products->first()->id)->toBe($product->id)
        ->and($product->category)->not->toBeNull();
});

test('only one default address per user', function () {
    $user = User::factory()->create();
    $first = Address::factory()->default()->create(['user_id' => $user->id]);
    $second = Address::factory()->default()->create(['user_id' => $user->id]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

test('sale window controls effective price', function () {
    $variant = ProductVariant::factory()->create([
        'price_sen' => 10000,
        'sale_price_sen' => 7000,
        'sale_starts_at' => now()->subHour(),
        'sale_ends_at' => now()->addHour(),
    ]);

    expect($variant->effectivePriceSen())->toBe(7000)
        ->and($variant->discountPercent())->toBe(30);

    $expired = ProductVariant::factory()->create([
        'price_sen' => 10000,
        'sale_price_sen' => 7000,
        'sale_starts_at' => now()->subDays(2),
        'sale_ends_at' => now()->subDay(),
    ]);

    expect($expired->effectivePriceSen())->toBe(10000)
        ->and($expired->discountPercent())->toBeNull();
});
