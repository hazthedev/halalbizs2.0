<?php

use App\Models\Product;
use App\Models\Review;
use Database\Seeders\DemoReviewsSeeder;
use Database\Seeders\RoleSeeder;

/**
 * The seeder builds real Completed sub-orders + reviews so ReviewObserver fills
 * product/store aggregates. This proves it produces data and is idempotent.
 */
it('seeds demo reviews and fills product rating aggregates', function () {
    $this->seed(RoleSeeder::class);
    Product::factory()->count(25)->create(); // each factory product gets a default variant

    $this->seed(DemoReviewsSeeder::class);

    expect(Review::count())->toBeGreaterThan(0)
        ->and(Product::query()->where('rating_count', '>', 0)->where('rating_avg', '>', 0)->exists())->toBeTrue();

    // Every seeded review is a verified purchase (hangs off a Completed order item).
    expect(Review::query()->whereNull('order_item_id')->count())->toBe(0);
});

it('is idempotent — a second run adds nothing', function () {
    $this->seed(RoleSeeder::class);
    Product::factory()->count(10)->create();

    $this->seed(DemoReviewsSeeder::class);
    $count = Review::count();

    $this->seed(DemoReviewsSeeder::class);

    expect(Review::count())->toBe($count);
});
