<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Livewire\Storefront\ProductDetail;
use App\Livewire\Storefront\StorePage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Livewire\Livewire;

function sellerRatingStore(): Store
{
    return Store::factory()->approved()->create();
}

function sellerRatingSubOrder(User $buyer, Store $store): SubOrder
{
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    return SubOrder::factory()->status(SubOrderStatus::Completed)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
    ]);
}

/** Snapshot row whose product belongs to the sub-order's store. */
function sellerRatingItem(SubOrder $subOrder, ?Product $product = null): OrderItem
{
    $product ??= Product::factory()->create(['store_id' => $subOrder->store_id]);

    return $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);
}

// ── Saving ──────────────────────────────────────────────────────────────

test('the seller service rating saves onto the FIRST review of the sub-order only and aggregates on the store', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();
    $subOrder = sellerRatingSubOrder($buyer, $store);
    $itemA = sellerRatingItem($subOrder);
    $itemB = sellerRatingItem($subOrder);

    $component = Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$itemA->id}", 5)
        ->set('sellerRating', 4)
        ->set('sellerComment', 'Fast replies and careful packing.')
        ->call('submit', $itemA->id)
        ->assertHasNoErrors();

    $reviewA = Review::query()->where('order_item_id', $itemA->id)->firstOrFail();

    expect($reviewA->seller_rating)->toBe(4)
        ->and($reviewA->seller_comment)->toBe('Fast replies and careful packing.');

    $store->refresh();

    expect((float) $store->service_rating_avg)->toBe(4.0)
        ->and((int) $store->service_rating_count)->toBe(1);

    // Second item review — even forcing the input, the rating is NOT duplicated.
    $component
        ->set("ratings.{$itemB->id}", 3)
        ->set('sellerRating', 5)
        ->call('submit', $itemB->id)
        ->assertHasNoErrors();

    $reviewB = Review::query()->where('order_item_id', $itemB->id)->firstOrFail();

    expect($reviewB->seller_rating)->toBeNull()
        ->and($reviewB->seller_comment)->toBeNull();

    $store->refresh();

    expect((float) $store->service_rating_avg)->toBe(4.0)
        ->and((int) $store->service_rating_count)->toBe(1);
});

test('the seller rating is optional — reviews save without one and aggregates stay at zero', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();
    $subOrder = sellerRatingSubOrder($buyer, $store);
    $item = sellerRatingItem($subOrder);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->call('submit', $item->id)
        ->assertHasNoErrors();

    expect(Review::sole()->seller_rating)->toBeNull()
        ->and((int) $store->fresh()->service_rating_count)->toBe(0)
        ->and((float) $store->fresh()->service_rating_avg)->toBe(0.0);
});

test('a seller rating outside 1–5 is rejected', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();
    $subOrder = sellerRatingSubOrder($buyer, $store);
    $item = sellerRatingItem($subOrder);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->set('sellerRating', 9)
        ->call('submit', $item->id)
        ->assertHasErrors(['sellerRating' => 'between']);

    expect(Review::count())->toBe(0);
});

// ── Aggregates across sub-orders + hide ─────────────────────────────────

test('store service aggregates average across sub-orders and recalculate when a review is hidden', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();

    $first = sellerRatingItem(sellerRatingSubOrder($buyer, $store));
    $second = sellerRatingItem(sellerRatingSubOrder($buyer, $store));

    Review::create([
        'order_item_id' => $first->id,
        'product_id' => $first->product_id,
        'store_id' => $store->id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'seller_rating' => 5,
    ]);

    $low = Review::create([
        'order_item_id' => $second->id,
        'product_id' => $second->product_id,
        'store_id' => $store->id,
        'user_id' => $buyer->id,
        'rating' => 4,
        'seller_rating' => 3,
    ]);

    $store->refresh();

    expect((float) $store->service_rating_avg)->toBe(4.0)
        ->and((int) $store->service_rating_count)->toBe(2);

    $low->update(['is_hidden' => true]);

    $store->refresh();

    expect((float) $store->service_rating_avg)->toBe(5.0)
        ->and((int) $store->service_rating_count)->toBe(1);

    $low->update(['is_hidden' => false]);

    $store->refresh();

    expect((float) $store->service_rating_avg)->toBe(4.0)
        ->and((int) $store->service_rating_count)->toBe(2);
});

// ── Display ─────────────────────────────────────────────────────────────

test('the PDP seller card and store page header show the seller service rating', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $item = sellerRatingItem(sellerRatingSubOrder($buyer, $store), $product);

    Review::create([
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'store_id' => $store->id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'seller_rating' => 4,
    ]);

    Livewire::test(ProductDetail::class, ['product' => $product->fresh()])
        ->assertSee('Seller service')
        ->assertSee('4.0');

    Livewire::test(StorePage::class, ['store' => $store->fresh()])
        ->assertSee('Seller service')
        ->assertSee('4.0');
});

test('the seller rating star row renders once per sub-order panel and disappears once rated', function () {
    $buyer = User::factory()->create();
    $store = sellerRatingStore();
    $subOrder = sellerRatingSubOrder($buyer, $store);
    $itemA = sellerRatingItem($subOrder);
    sellerRatingItem($subOrder);

    $component = Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->call('toggle');

    // Two pending items, ONE seller rating block.
    expect(substr_count($component->html(), 'Rate the seller&#039;s service'))->toBe(1);

    $component
        ->set("ratings.{$itemA->id}", 5)
        ->set('sellerRating', 5)
        ->call('submit', $itemA->id)
        ->assertHasNoErrors()
        ->assertDontSee("Rate the seller's service");
});
