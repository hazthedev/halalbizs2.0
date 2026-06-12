<?php

use App\Models\Category;
use App\Models\Store;
use App\Services\CommissionResolver;
use App\Settings\CommissionSettings;

beforeEach(function () {
    $settings = app(CommissionSettings::class);
    $settings->global_rate = 5.00;
    $settings->save();
});

test('seller override wins over everything', function () {
    $store = Store::factory()->approved()->create(['commission_rate' => 12.5]);
    $category = Category::factory()->create(['commission_rate' => 8.0]);

    expect(app(CommissionResolver::class)->resolve($store, $category))->toBe(12.5);
});

test('category rate wins when store has no override', function () {
    $store = Store::factory()->approved()->create(['commission_rate' => null]);
    $category = Category::factory()->create(['commission_rate' => 8.0]);

    expect(app(CommissionResolver::class)->resolve($store, $category))->toBe(8.0);
});

test('category chain walks upward to an ancestor rate', function () {
    $store = Store::factory()->approved()->create(['commission_rate' => null]);
    $grandparent = Category::factory()->create(['commission_rate' => 7.0]);
    $parent = Category::factory()->create(['parent_id' => $grandparent->id, 'commission_rate' => null]);
    $leaf = Category::factory()->create(['parent_id' => $parent->id, 'commission_rate' => null]);

    expect(app(CommissionResolver::class)->resolve($store, $leaf))->toBe(7.0);
});

test('falls back to the global default', function () {
    $store = Store::factory()->approved()->create(['commission_rate' => null]);
    $category = Category::factory()->create(['commission_rate' => null]);

    expect(app(CommissionResolver::class)->resolve($store, $category))->toBe(5.0)
        ->and(app(CommissionResolver::class)->resolve($store))->toBe(5.0);
});
