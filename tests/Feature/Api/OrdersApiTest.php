<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestCase;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

/** @return array{0: User, 1: TestCase} */
function apiOrdersUser(): array
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    app('auth')->forgetGuards();

    return [$user, test()->withToken($user->createToken('pest-phone')->plainTextToken)];
}

function apiOrdersSubOrder(User $buyer, SubOrderStatus $status, array $orderAttributes = []): SubOrder
{
    $order = Order::factory()->create(array_merge(['user_id' => $buyer->id], $orderAttributes));

    return SubOrder::factory()->status($status)->create(['order_id' => $order->id]);
}

function apiOrdersItem(SubOrder $subOrder, ?Product $product = null, int $qty = 1): OrderItem
{
    $product ??= Product::factory()->create();

    return $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => $qty,
        'line_total_sen' => 2500 * $qty,
    ]);
}

test('buckets are the web tabs and the list filters by them, newest first', function () {
    [$user, $http] = apiOrdersUser();

    $confirmed = apiOrdersSubOrder($user, SubOrderStatus::Confirmed);
    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);
    $completed = apiOrdersSubOrder($user, SubOrderStatus::Completed);
    $unpaid = apiOrdersSubOrder($user, SubOrderStatus::PendingPayment, [
        'payment_method' => PaymentMethod::Ipay88, 'payment_status' => PaymentStatus::Pending, 'expires_at' => now()->addHour(),
    ]);
    apiOrdersItem($confirmed);

    $http->getJson('/api/v1/orders/buckets')
        ->assertOk()
        ->assertJsonPath('data.*.key', ['to-pay', 'to-ship', 'to-receive', 'completed', 'cancelled', 'return-refund'])
        ->assertJsonPath('data.0.label', __('To Pay'))
        ->assertJsonPath('data.0.count', 1)
        ->assertJsonPath('data.1.count', 1)
        ->assertJsonPath('data.2.count', 1)
        ->assertJsonPath('data.3.count', 1);

    $http->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('data.0.id', $unpaid->id)
        ->assertJsonPath('data.3.id', $confirmed->id);

    $http->getJson('/api/v1/orders?bucket=to-ship')
        ->assertOk()
        ->assertJsonPath('meta.bucket', 'to-ship')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $confirmed->id)
        ->assertJsonPath('data.0.status', 'confirmed')
        ->assertJsonPath('data.0.status_label', __('Confirmed'))
        ->assertJsonPath('data.0.can.cancel', true)
        ->assertJsonPath('data.0.can.confirm_received', false)
        ->assertJsonPath('data.0.can.return', false)
        ->assertJsonPath('data.0.can.review', false)
        ->assertJsonPath('data.0.items.0.name', $confirmed->items->first()->product_name)
        ->assertJsonPath('data.0.items.0.unit_price_sen', 2500)
        ->assertJsonPath('data.0.items.0.variant_id', $confirmed->items->first()->product_variant_id)
        ->assertJsonPath('data.0.items.0.reviewed', false)
        ->assertJsonPath('data.0.payment.method', 'cod')
        ->assertJsonPath('data.0.return_request', null)
        ->assertJsonStructure(['data' => [['id', 'sub_order_no', 'order_no', 'store' => ['id', 'slug', 'name', 'logo'], 'subtotal_sen', 'shipping_sen', 'discount_sen', 'total_sen', 'placed_at']]]);

    $http->getJson('/api/v1/orders?bucket=to-pay')->assertOk()->assertJsonPath('data.0.id', $unpaid->id)->assertJsonPath('data.0.can.cancel', true);
    $http->getJson('/api/v1/orders?bucket=to-receive')->assertOk()->assertJsonPath('data.0.id', $shipped->id);
    $http->getJson('/api/v1/orders?bucket=completed')->assertOk()->assertJsonPath('data.0.id', $completed->id);
    $http->getJson('/api/v1/orders?bucket=nope')->assertStatus(422);
});

test('detail adds tracking, history and the address snapshot; strangers get a 404', function () {
    [$user, $http] = apiOrdersUser();
    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);
    apiOrdersItem($shipped);

    $http->getJson("/api/v1/orders/{$shipped->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $shipped->id)
        ->assertJsonPath('data.tracking.no', $shipped->tracking_no)
        ->assertJsonPath('data.tracking.courier', 'J&T Express')
        ->assertJsonPath('data.history.0.status', 'shipped')
        ->assertJsonPath('data.address.recipient_name', $shipped->order->shipping_address['recipient_name'])
        ->assertJsonStructure(['data' => ['history' => [['status', 'label', 'at', 'note']]]]);

    [, $stranger] = apiOrdersUser();
    $stranger->getJson("/api/v1/orders/{$shipped->id}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
    $stranger->postJson("/api/v1/orders/{$shipped->id}/cancel")->assertNotFound();
});

test('cancel works while confirmed and is a 409 invalid_transition once shipped', function () {
    [$user, $http] = apiOrdersUser();
    $confirmed = apiOrdersSubOrder($user, SubOrderStatus::Confirmed);
    $product = Product::factory()->create();
    $product->variants->first()->update(['stock' => 3]);
    apiOrdersItem($confirmed, $product, 2);

    $http->postJson("/api/v1/orders/{$confirmed->id}/cancel", ['reason' => 'Changed my mind'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.can.cancel', false);

    expect($confirmed->fresh()->cancel_reason)->toBe('Changed my mind')
        ->and($product->variants->first()->fresh()->stock)->toBe(5)
        ->and($confirmed->fresh()->statusHistories()->count())->toBe(2);

    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);

    $http->postJson("/api/v1/orders/{$shipped->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    expect($shipped->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('cancelling an unpaid online order cancels every pending sub-order of it', function () {
    [$user, $http] = apiOrdersUser();
    $first = apiOrdersSubOrder($user, SubOrderStatus::PendingPayment, [
        'payment_method' => PaymentMethod::Ipay88, 'payment_status' => PaymentStatus::Pending, 'expires_at' => now()->addHour(),
    ]);
    $second = SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $first->order_id]);

    $http->postJson("/api/v1/orders/{$first->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect($second->fresh()->status)->toBe(SubOrderStatus::Cancelled);
});

test('confirm received completes a delivered sub-order and refuses a shipped one', function () {
    [$user, $http] = apiOrdersUser();
    $delivered = apiOrdersSubOrder($user, SubOrderStatus::Delivered);
    apiOrdersItem($delivered);

    $http->postJson("/api/v1/orders/{$delivered->id}/confirm-received")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.can.review', true);

    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);
    $http->postJson("/api/v1/orders/{$shipped->id}/confirm-received")->assertStatus(409)->assertJsonPath('error.code', 'invalid_transition');
});

test('a return request with photos goes through ReturnService', function () {
    [$user, $http] = apiOrdersUser();
    $delivered = apiOrdersSubOrder($user, SubOrderStatus::Delivered);
    apiOrdersItem($delivered);
    $reason = ReturnReason::create(['label' => ['en' => 'Damaged on arrival'], 'is_active' => true, 'position' => 1]);
    ReturnReason::create(['label' => ['en' => 'Retired reason'], 'is_active' => false, 'position' => 2]);

    $http->getJson('/api/v1/return-reasons')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', ['id' => $reason->id, 'label' => 'Damaged on arrival']);

    $http->post("/api/v1/orders/{$delivered->id}/return", [
        'reason_id' => $reason->id,
        'note' => 'Box was crushed',
        'photos' => [UploadedFile::fake()->image('box.jpg'), UploadedFile::fake()->image('box2.jpg')],
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.status', ReturnStatus::Requested->value)
        ->assertJsonPath('data.reason', 'Damaged on arrival');

    $delivered->refresh();

    expect($delivered->status)->toBe(SubOrderStatus::ReturnRequested)
        ->and($delivered->returnRequest->description)->toBe('Box was crushed')
        ->and($delivered->returnRequest->getMedia('photos'))->toHaveCount(2);

    $http->getJson("/api/v1/orders/{$delivered->id}")
        ->assertJsonPath('data.return_request.status', 'requested')
        ->assertJsonPath('data.can.return', false);

    // A second request on the same sub-order is a state conflict.
    $http->postJson("/api/v1/orders/{$delivered->id}/return", ['reason_id' => $reason->id])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'return_not_allowed')
        ->assertJsonPath('error.message', __('This order is no longer eligible for a return.'));

    // Shipped orders are not eligible either; an inactive reason fails validation first.
    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);
    $http->postJson("/api/v1/orders/{$shipped->id}/return", ['reason_id' => $reason->id])->assertStatus(409)->assertJsonPath('error.code', 'return_not_allowed');
    $http->postJson("/api/v1/orders/{$shipped->id}/return", ['reason_id' => $reason->id + 1])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
});

test('a review goes through ReviewService once per item, three submits a minute', function () {
    [$user, $http] = apiOrdersUser();
    $completed = apiOrdersSubOrder($user, SubOrderStatus::Completed);
    $item = apiOrdersItem($completed);

    $http->post("/api/v1/orders/{$completed->id}/review", [
        'items' => [['order_item_id' => $item->id, 'rating' => 5, 'body' => 'Fresh and well packed, would buy again.', 'photos' => [UploadedFile::fake()->image('r.jpg')]]],
        'seller_rating' => 4,
        'seller_comment' => 'Quick replies',
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.items.0.reviewed', true)
        ->assertJsonPath('data.can.review', false);

    $review = Review::where('order_item_id', $item->id)->firstOrFail();

    expect($review->rating)->toBe(5)
        ->and($review->seller_rating)->toBe(4)
        ->and($review->comment)->toBe('Fresh and well packed, would buy again.')
        ->and($review->getMedia('photos'))->toHaveCount(1);

    $http->postJson("/api/v1/orders/{$completed->id}/review", ['items' => [['order_item_id' => $item->id, 'rating' => 3]]])
        ->assertStatus(422)
        ->assertJsonPath('error.message', __('You already reviewed this item.'));

    expect(Review::count())->toBe(1);

    // Validation mirrors the web form.
    $http->postJson("/api/v1/orders/{$completed->id}/review", ['items' => [['order_item_id' => $item->id, 'rating' => 9]]])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['items.0.rating']]]);

    // ReviewOrder parity: 3 per minute, then 429.
    $http->postJson("/api/v1/orders/{$completed->id}/review", ['items' => [['order_item_id' => $item->id, 'rating' => 4]]])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'throttled');
});

test('a review on a sub-order that is not completed is refused with the service message', function () {
    [$user, $http] = apiOrdersUser();
    $shipped = apiOrdersSubOrder($user, SubOrderStatus::Shipped);
    $item = apiOrdersItem($shipped);

    $http->postJson("/api/v1/orders/{$shipped->id}/review", ['items' => [['order_item_id' => $item->id, 'rating' => 4]]])
        ->assertStatus(422)
        ->assertJsonPath('error.message', __('You can rate this order once it is completed.'));

    expect(Review::count())->toBe(0);
});

test('an unpaid order past its payment window reads Payment expired, not Pending payment', function () {
    [$user, $http] = apiOrdersUser();
    $lapsed = apiOrdersSubOrder($user, SubOrderStatus::PendingPayment, [
        'payment_method' => PaymentMethod::Ipay88, 'payment_status' => PaymentStatus::Pending, 'expires_at' => now()->subMinute(),
    ]);

    $http->getJson('/api/v1/orders')->assertOk()
        ->assertJsonPath('data.0.id', $lapsed->id)
        ->assertJsonPath('data.0.status_label', 'Payment expired')
        ->assertJsonPath('data.0.can.cancel', false);
    $http->getJson('/api/v1/orders?bucket=to-pay')->assertOk()->assertJsonPath('meta.total', 0);
    $http->withHeader('Accept-Language', 'ms')->getJson('/api/v1/orders')->assertJsonPath('data.0.status_label', 'Tempoh bayaran tamat');
});

test('order status labels and bucket labels come back in Bahasa Melayu for Accept-Language ms', function () {
    [$user, $http] = apiOrdersUser();
    apiOrdersSubOrder($user, SubOrderStatus::Confirmed);
    apiOrdersSubOrder($user, SubOrderStatus::PendingPayment, [
        'payment_method' => PaymentMethod::Ipay88, 'payment_status' => PaymentStatus::Pending, 'expires_at' => now()->addHour(),
    ]);
    $http->withHeader('Accept-Language', 'ms');

    $http->getJson('/api/v1/orders/buckets')->assertJsonPath('data.0.label', 'Untuk Dibayar');
    expect($http->getJson('/api/v1/orders')->json('data.*.status_label'))->toBe(['Menunggu bayaran', 'Disahkan']);
});
