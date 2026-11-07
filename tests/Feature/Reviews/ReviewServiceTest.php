<?php

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function reviewServiceSubOrder(User $buyer, SubOrderStatus $status = SubOrderStatus::Completed): SubOrder
{
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    return SubOrder::factory()->status($status)->create(['order_id' => $order->id]);
}

function reviewServiceItem(SubOrder $subOrder, ?Product $product = null): OrderItem
{
    $product ??= Product::factory()->create(['store_id' => $subOrder->store_id]);

    return $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);
}

test('submit() writes one review per item with photos, puts the seller rating on the first only and refreshes aggregates', function () {
    Storage::fake('public');
    $buyer = User::factory()->create();
    $subOrder = reviewServiceSubOrder($buyer);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);
    $itemA = reviewServiceItem($subOrder, $product);
    $itemB = reviewServiceItem($subOrder, $product);

    app(ReviewService::class)->submit($subOrder, $buyer, [
        ['order_item_id' => $itemA->id, 'rating' => 4, 'body' => ' Really fresh and well packed. ', 'photos' => [UploadedFile::fake()->image('unboxing.jpg', 600, 600)]],
        ['order_item_id' => $itemB->id, 'rating' => 5],
    ], sellerRating: 3, sellerComment: 'Slow replies.');

    $reviewA = Review::where('order_item_id', $itemA->id)->firstOrFail();
    $reviewB = Review::where('order_item_id', $itemB->id)->firstOrFail();

    expect($reviewA->rating)->toBe(4)
        ->and($reviewA->comment)->toBe('Really fresh and well packed.')
        ->and($reviewA->user_id)->toBe($buyer->id)
        ->and($reviewA->store_id)->toBe($subOrder->store_id)
        ->and($reviewA->seller_rating)->toBe(3)
        ->and($reviewA->seller_comment)->toBe('Slow replies.')
        ->and($reviewA->getMedia('photos'))->toHaveCount(1)
        ->and($reviewB->comment)->toBeNull()
        ->and($reviewB->seller_rating)->toBeNull();

    $store = $subOrder->store->fresh();

    expect((float) $product->fresh()->rating_avg)->toBe(4.5)
        ->and((int) $product->fresh()->rating_count)->toBe(2)
        ->and((float) $store->service_rating_avg)->toBe(3.0)
        ->and((int) $store->service_rating_count)->toBe(1)
        ->and(app(ReviewService::class)->sellerRated($subOrder))->toBeTrue();
});

test('submit() refuses a stranger, an incomplete order, a duplicate and a bad rating — and rolls back the batch', function () {
    $buyer = User::factory()->create();
    $service = app(ReviewService::class);

    $pending = reviewServiceSubOrder($buyer, SubOrderStatus::Delivered);
    $pendingItem = reviewServiceItem($pending);
    expect(fn () => $service->submit($pending, $buyer, [['order_item_id' => $pendingItem->id, 'rating' => 5]]))
        ->toThrow(InvalidArgumentException::class, 'once it is completed');

    $subOrder = reviewServiceSubOrder($buyer);
    $itemA = reviewServiceItem($subOrder);
    $itemB = reviewServiceItem($subOrder);

    expect(fn () => $service->submit($subOrder, User::factory()->create(), [['order_item_id' => $itemA->id, 'rating' => 5]]))
        ->toThrow(AuthorizationException::class);

    // Second item invalid → the first review is rolled back too.
    expect(fn () => $service->submit($subOrder, $buyer, [
        ['order_item_id' => $itemA->id, 'rating' => 5],
        ['order_item_id' => $itemB->id, 'rating' => 6],
    ]))->toThrow(InvalidArgumentException::class, 'Tap a star');

    expect(Review::count())->toBe(0);

    $service->submit($subOrder, $buyer, [['order_item_id' => $itemA->id, 'rating' => 5]]);

    expect(fn () => $service->submit($subOrder, $buyer, [['order_item_id' => $itemA->id, 'rating' => 4]]))
        ->toThrow(InvalidArgumentException::class, 'already reviewed');

    expect(Review::count())->toBe(1);
});

test('submit() refuses more than 5 photos, non-images and oversized photos before writing anything', function () {
    Storage::fake('public');
    $buyer = User::factory()->create();
    $subOrder = reviewServiceSubOrder($buyer);
    $item = reviewServiceItem($subOrder);
    $service = app(ReviewService::class);
    $submit = fn (array $photos) => fn () => $service->submit($subOrder, $buyer, [['order_item_id' => $item->id, 'rating' => 5, 'photos' => $photos]]);

    expect($submit(collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"))->all()))
        ->toThrow(InvalidArgumentException::class, 'Up to 5 photos per review.');

    expect($submit([UploadedFile::fake()->create('evil.html', 10, 'text/html')]))
        ->toThrow(InvalidArgumentException::class, 'Photos must be images');

    expect($submit([UploadedFile::fake()->image('anim.gif')]))
        ->toThrow(InvalidArgumentException::class, 'Photos must be images');

    expect($submit([UploadedFile::fake()->image('huge.jpg')->size(5000)]))
        ->toThrow(InvalidArgumentException::class, 'Each photo must be 4MB or smaller.');

    expect(Review::count())->toBe(0);
});

test('assertReviewable() is the gate the web panel runs before spending a Turnstile token', function () {
    $buyer = User::factory()->create();
    $service = app(ReviewService::class);

    $pending = reviewServiceSubOrder($buyer, SubOrderStatus::Delivered);
    $pendingItem = reviewServiceItem($pending);
    expect(fn () => $service->assertReviewable($pending, $buyer, $pendingItem->id))
        ->toThrow(InvalidArgumentException::class, 'once it is completed');

    $subOrder = reviewServiceSubOrder($buyer);
    $item = reviewServiceItem($subOrder);

    expect($service->assertReviewable($subOrder, $buyer, $item->id)->id)->toBe($item->id)
        ->and(fn () => $service->assertReviewable($subOrder, User::factory()->create(), $item->id))
        ->toThrow(AuthorizationException::class);

    $service->submit($subOrder, $buyer, [['order_item_id' => $item->id, 'rating' => 5]]);

    expect(fn () => $service->assertReviewable($subOrder, $buyer, $item->id))
        ->toThrow(InvalidArgumentException::class, 'already reviewed');
});
