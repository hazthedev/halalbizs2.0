<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Catalog\Reviews as AdminReviews;
use App\Livewire\Seller\Reviews\Index as SellerReviews;
use App\Livewire\Storefront\Account\Orders;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function reviewsBuyer(): User
{
    return User::factory()->create();
}

function reviewsSubOrder(User $buyer, SubOrderStatus $status = SubOrderStatus::Completed, ?Store $store = null): SubOrder
{
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    $attributes = ['order_id' => $order->id];

    if ($store !== null) {
        $attributes['store_id'] = $store->id;
    }

    return SubOrder::factory()->status($status)->create($attributes);
}

/** Snapshot row whose product belongs to the sub-order's store (the post-checkout shape). */
function reviewsItem(SubOrder $subOrder, ?Product $product = null, ?string $variantLabel = null): OrderItem
{
    $product ??= Product::factory()->create(['store_id' => $subOrder->store_id]);

    return $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => $variantLabel,
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);
}

function reviewsMake(OrderItem $item, int $rating = 5, array $attributes = []): Review
{
    $subOrder = $item->subOrder;

    return Review::create(array_merge([
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'store_id' => $subOrder->store_id,
        'user_id' => $subOrder->order->user_id,
        'rating' => $rating,
        'comment' => 'Solid quality and fast delivery, very pleased.',
    ], $attributes));
}

function reviewsAdmin(): User
{
    (new RoleSeeder)->run();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

// ── Buyer submission gate ───────────────────────────────────────────────

test('review submit is refused while the sub-order is not completed', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer, SubOrderStatus::Delivered);
    $item = reviewsItem($subOrder);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->call('submit', $item->id)
        ->assertDispatched('toast');

    expect(Review::count())->toBe(0);
});

test("a stranger cannot mount another buyer's review panel", function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    reviewsItem($subOrder);

    Livewire::actingAs(reviewsBuyer())
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->assertForbidden();
});

test('an item can only be reviewed once', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $item = reviewsItem($subOrder);
    reviewsMake($item, 4);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->call('submit', $item->id)
        ->assertDispatched('toast');

    expect(Review::count())->toBe(1)
        ->and(Review::firstOrFail()->rating)->toBe(4);
});

test('a comment shorter than 10 characters is rejected', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $item = reviewsItem($subOrder);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->set("comments.{$item->id}", 'too short')
        ->call('submit', $item->id)
        ->assertHasErrors(["comments.{$item->id}" => 'min']);

    expect(Review::count())->toBe(0);
});

// ── Buyer submission + aggregates ───────────────────────────────────────

test('submitting reviews stores photos and updates product and store aggregates', function () {
    Storage::fake('public');

    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);
    $itemA = reviewsItem($subOrder, $product);
    $itemB = reviewsItem($subOrder, $product);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$itemA->id}", 4)
        ->set("comments.{$itemA->id}", 'Really fresh and well packed, will reorder.')
        ->set("photos.{$itemA->id}", [UploadedFile::fake()->image('unboxing.jpg', 600, 600)])
        ->call('submit', $itemA->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast')
        ->set("ratings.{$itemB->id}", 5)
        ->call('submit', $itemB->id)
        ->assertHasNoErrors();

    $reviewA = Review::query()->where('order_item_id', $itemA->id)->firstOrFail();

    expect($reviewA->rating)->toBe(4)
        ->and($reviewA->comment)->toBe('Really fresh and well packed, will reorder.')
        ->and($reviewA->user_id)->toBe($buyer->id)
        ->and($reviewA->product_id)->toBe($product->id)
        ->and($reviewA->store_id)->toBe($subOrder->store_id)
        ->and($reviewA->getMedia('photos'))->toHaveCount(1);

    $product->refresh();
    $store = $subOrder->store->fresh();

    expect((float) $product->rating_avg)->toBe(4.5)
        ->and((int) $product->rating_count)->toBe(2)
        ->and((float) $store->rating_avg)->toBe(4.5)
        ->and((int) $store->rating_count)->toBe(2);
});

test('review submissions are rate limited to 3 per minute per user', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $items = collect(range(1, 4))->map(fn () => reviewsItem($subOrder));

    $component = Livewire::actingAs($buyer)->test(ReviewOrder::class, ['subOrder' => $subOrder]);

    foreach ($items as $item) {
        $component->set("ratings.{$item->id}", 5)->call('submit', $item->id);
    }

    expect(Review::count())->toBe(3);
});

test('completed cards offer Rate order until every item is reviewed, then show Reviewed', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $item = reviewsItem($subOrder);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setTab', 'completed')
        ->assertSee('Rate order')
        ->assertDontSee('Reviewed ✓');

    reviewsMake($item);

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setTab', 'completed')
        ->assertDontSee('Rate order')
        ->assertSee('Reviewed ✓');
});

// ── Observer: hide recalculates aggregates ──────────────────────────────

test('hiding a review recalculates aggregates from visible reviews only', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    reviewsMake(reviewsItem($subOrder, $product), 5);
    $three = reviewsMake(reviewsItem($subOrder, $product), 3);

    expect((float) $product->refresh()->rating_avg)->toBe(4.0)
        ->and((int) $product->rating_count)->toBe(2);

    $three->update(['is_hidden' => true]);

    $product->refresh();
    $store = $subOrder->store->fresh();

    expect((float) $product->rating_avg)->toBe(5.0)
        ->and((int) $product->rating_count)->toBe(1)
        ->and((float) $store->rating_avg)->toBe(5.0)
        ->and((int) $store->rating_count)->toBe(1);

    $three->update(['is_hidden' => false]);

    expect((float) $product->refresh()->rating_avg)->toBe(4.0)
        ->and((int) $product->rating_count)->toBe(2);
});

// ── PDP reviews tab ─────────────────────────────────────────────────────

test('PDP reviews tab shows summary, distribution, masked reviewer and excludes hidden reviews', function () {
    $buyer = User::factory()->create(['name' => 'Nurul Aina']);
    $subOrder = reviewsSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    reviewsMake(reviewsItem($subOrder, $product, 'Blue / M'), 5, ['comment' => 'Absolutely loved the texture and taste.']);
    reviewsMake(reviewsItem($subOrder, $product), 5, ['comment' => 'Second five star praise goes right here.']);
    reviewsMake(reviewsItem($subOrder, $product), 3, ['comment' => 'Decent but the packaging dented in transit.']);
    reviewsMake(reviewsItem($subOrder, $product), 1, ['comment' => 'This hidden rant should never be visible.', 'is_hidden' => true]);

    Livewire::test(ProductDetail::class, ['product' => $product->fresh()])
        ->assertSee('4.3') // (5+5+3)/3, hidden 1★ excluded
        ->assertSee('Nurul A.')
        ->assertDontSee('Nurul Aina')
        ->assertSee('Blue / M')
        ->assertSee('Absolutely loved the texture and taste.')
        ->assertSee('5-star reviews: 2')
        ->assertSee('3-star reviews: 1')
        ->assertDontSee('This hidden rant should never be visible.');
});

test('the With photos filter shows only reviews carrying photos', function () {
    Storage::fake('public');

    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    $withPhoto = reviewsMake(reviewsItem($subOrder, $product), 5, ['comment' => 'Came with a lovely photo attached here.']);
    $photoFile = UploadedFile::fake()->image('snap.jpg', 400, 400);
    $withPhoto->addMedia($photoFile->getRealPath())
        ->usingFileName('snap.jpg')
        ->toMediaCollection('photos');

    reviewsMake(reviewsItem($subOrder, $product), 4, ['comment' => 'No photo on this one, plain words only.']);

    Livewire::test(ProductDetail::class, ['product' => $product->fresh()])
        ->assertSee('Came with a lovely photo attached here.')
        ->assertSee('No photo on this one, plain words only.')
        ->call('setReviewFilter', 'photos')
        ->assertSee('Came with a lovely photo attached here.')
        ->assertDontSee('No photo on this one, plain words only.');
});

// ── Seller replies ──────────────────────────────────────────────────────

test('seller replies once, can edit within 24 hours, then the reply locks', function () {
    $store = Store::factory()->approved()->create();
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer, SubOrderStatus::Completed, $store);
    $review = reviewsMake(reviewsItem($subOrder));

    $seller = $store->user;

    Livewire::actingAs($seller)
        ->test(SellerReviews::class)
        ->call('startReply', $review->id)
        ->set('replyText', 'Thank you for the support!')
        ->call('saveReply')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $review->refresh();
    $firstRepliedAt = $review->seller_replied_at;

    expect($review->seller_reply)->toBe('Thank you for the support!')
        ->and($firstRepliedAt)->not->toBeNull();

    // Edit inside the 24h window — allowed, and the window does not reset.
    $this->travel(2)->hours();

    Livewire::actingAs($seller)
        ->test(SellerReviews::class)
        ->call('startReply', $review->id)
        ->set('replyText', 'Thank you — updated with care instructions.')
        ->call('saveReply')
        ->assertHasNoErrors();

    $review->refresh();

    expect($review->seller_reply)->toBe('Thank you — updated with care instructions.')
        ->and($review->seller_replied_at->equalTo($firstRepliedAt))->toBeTrue();

    // 25h after the FIRST reply — locked, even when forcing the form state.
    $this->travel(23)->hours();

    Livewire::actingAs($seller)
        ->test(SellerReviews::class)
        ->assertSee('Replies lock 24 hours after posting.')
        ->call('startReply', $review->id)
        ->assertDispatched('toast')
        ->set('replyingId', $review->id)
        ->set('replyText', 'Sneaky late edit.')
        ->call('saveReply');

    expect($review->fresh()->seller_reply)->toBe('Thank you — updated with care instructions.');
});

test("a seller cannot see or reply to another store's review", function () {
    $store = Store::factory()->approved()->create();
    $otherStore = Store::factory()->approved()->create();

    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer, SubOrderStatus::Completed, $otherStore);
    $review = reviewsMake(reviewsItem($subOrder), 2, ['comment' => 'Belongs to a different storefront entirely.']);

    Livewire::actingAs($store->user)
        ->test(SellerReviews::class)
        ->assertDontSee('Belongs to a different storefront entirely.')
        ->call('startReply', $review->id)
        ->assertNotFound();

    expect($review->fresh()->seller_reply)->toBeNull();
});

// ── Admin moderation ────────────────────────────────────────────────────

test('admin hide requires a reason, logs activity and recalculates aggregates', function () {
    $buyer = reviewsBuyer();
    $subOrder = reviewsSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    reviewsMake(reviewsItem($subOrder, $product), 5);
    $review = reviewsMake(reviewsItem($subOrder, $product), 1, ['comment' => 'Spam spam spam spam spam.']);

    $admin = reviewsAdmin();

    $component = Livewire::actingAs($admin)
        ->test(AdminReviews::class)
        ->call('startModeration', $review->id)
        ->call('confirmModeration')
        ->assertHasErrors(['moderationReason' => 'required']);

    expect($review->fresh()->is_hidden)->toBeFalse();

    $component
        ->set('moderationReason', 'Spam content — not a genuine review.')
        ->call('confirmModeration')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($review->fresh()->is_hidden)->toBeTrue();

    $activity = Activity::query()->where('description', 'review.hidden')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Review::class)
        ->and((int) $activity->subject_id)->toBe($review->id)
        ->and((int) $activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['reason'])->toBe('Spam content — not a genuine review.');

    expect((float) $product->refresh()->rating_avg)->toBe(5.0)
        ->and((int) $product->rating_count)->toBe(1);

    // Unhide with a reason restores the aggregates and logs review.unhidden.
    Livewire::actingAs($admin)
        ->test(AdminReviews::class)
        ->call('startModeration', $review->id)
        ->set('moderationReason', 'Appeal accepted — verified genuine purchase.')
        ->call('confirmModeration')
        ->assertHasNoErrors();

    expect($review->fresh()->is_hidden)->toBeFalse()
        ->and((int) $product->refresh()->rating_count)->toBe(2)
        ->and(Activity::query()->where('description', 'review.unhidden')->exists())->toBeTrue();
});
