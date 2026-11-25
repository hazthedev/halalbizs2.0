<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\ProductReviews;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Security-audit regression tests (T6 — storefront Livewire hardening).
 * Each test is written to fail against the pre-fix code: see the executor
 * report for the observed failure output.
 */
function hardeningBuyer(): User
{
    return User::factory()->create();
}

/** A live product with $count real (verified-purchase) reviews attached. */
function hardeningReviewedProduct(int $count): Product
{
    $buyer = hardeningBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    for ($i = 0; $i < $count; $i++) {
        $item = $subOrder->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()->id,
            'product_name' => $product->getTranslation('name', 'en'),
            'unit_price_sen' => 2500,
            'qty' => 1,
            'line_total_sen' => 2500,
        ]);

        Review::create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'store_id' => $subOrder->store_id,
            'user_id' => $buyer->id,
            'rating' => 5,
            'comment' => "Solid quality and fast delivery, review #{$i}.",
        ]);
    }

    return $product;
}

/** A single reviewable item + its review, for the markHelpful tests. */
function hardeningReviewedItem(): Review
{
    $buyer = hardeningBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    $item = $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    return Review::create([
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'store_id' => $subOrder->store_id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'comment' => 'Solid quality and fast delivery, very pleased.',
    ]);
}

// ── AL-M5: unbounded pagination props ───────────────────────────────────

test('an oversized reviewsLimit set from the client is clamped, not replayed straight into the query', function () {
    $product = hardeningReviewedProduct(60); // over the 50-review ceiling

    $reviews = Livewire::test(ProductReviews::class, ['product' => $product->fresh()])
        ->set('reviewsLimit', 5_000_000)
        ->viewData('reviews');

    expect($reviews->count())->toBeLessThanOrEqual(50)
        ->and($reviews->count())->toBeLessThan(60);
});

// ── AL-L3: markHelpful is not atomic ─────────────────────────────────────

test('markHelpful derives helpful_count from the pivot so repeated calls self-heal instead of drifting', function () {
    $buyer = hardeningBuyer();
    $otherVoter = hardeningBuyer();
    $review = hardeningReviewedItem();

    // Simulate the pre-existing drift the old increment()/decrement() code
    // could leave behind: one real pivot vote, but a stored count nowhere
    // near it.
    DB::table('review_helpfuls')->insert([
        'review_id' => $review->id,
        'user_id' => $otherVoter->id,
        'created_at' => now(),
    ]);
    $review->forceFill(['helpful_count' => 99])->save();

    $component = Livewire::actingAs($buyer)
        ->test(ProductReviews::class, ['product' => $review->product->fresh()]);

    // Buyer votes: pivot now holds otherVoter + buyer = 2, so the derived
    // count must be exactly 2 — never the old code's blind 99 + 1 = 100.
    $component->call('markHelpful', $review->id);
    expect($review->fresh()->helpful_count)->toBe(2);

    // Buyer repeats the call (double-click): un-votes, pivot back to just
    // otherVoter = 1.
    $component->call('markHelpful', $review->id);
    expect($review->fresh()->helpful_count)->toBe(1);

    // A third call re-votes: back to 2, never drifting past the true count.
    $component->call('markHelpful', $review->id);
    expect($review->fresh()->helpful_count)->toBe(2);
});
