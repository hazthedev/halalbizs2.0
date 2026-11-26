<?php

use App\Models\Product;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// HB-SPD3: collection() -> mapInto() passed the item index as `detail`, so items 1..n
// of every list were built as full detail pages (154 queries for a 24-item search).
$detailOnly = ['images', 'options', 'specs', 'metafields'];

test('list endpoints return list cards for every item, not detail pages', function (string $url) use ($detailOnly) {
    Product::factory()->count(4)->create(['name' => ['en' => 'Coklat test']]);

    $items = $this->getJson($url)->assertOk()->json('data');

    expect($items)->toHaveCount(4);
    foreach ($items as $item) {
        foreach ($detailOnly as $key) {
            expect($item)->not->toHaveKey($key);
        }
    }
})->with(['/api/v1/search?q=coklat', '/api/v1/products']);

test('search stays under a query budget', function () {
    Product::factory()->count(8)->create(['name' => ['en' => 'Coklat test']]);

    DB::enableQueryLog();
    $this->getJson('/api/v1/search?q=coklat')->assertOk();

    // Was 3 + ~6 per item beyond the first before the fix; now flat in the item count.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(30);
});

test('the product detail endpoint still returns the full detail', function () use ($detailOnly) {
    $product = Product::factory()->create();

    $data = $this->getJson("/api/v1/products/{$product->slug}")->assertOk()->json('data');

    foreach ($detailOnly as $key) {
        expect($data)->toHaveKey($key);
    }
});

test('the rate limiter stays off the database cache store and still answers 429', function () {
    expect(config('cache.limiter'))->not->toBeNull()->not->toBe('database');

    foreach (range(1, 60) as $i) {
        $this->getJson('/api/v1/categories')->assertOk();
    }
    $this->getJson('/api/v1/categories')->assertStatus(429);
});
