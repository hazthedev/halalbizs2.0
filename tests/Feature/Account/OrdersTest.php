<?php

use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\OrderDetail;
use App\Livewire\Storefront\Account\Orders;
use App\Models\CancellationReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function ordersBuyer(): User
{
    return User::factory()->create();
}

function ordersSubOrder(User $buyer, SubOrderStatus $status, array $orderAttributes = []): SubOrder
{
    $order = Order::factory()->create(array_merge(['user_id' => $buyer->id], $orderAttributes));

    return SubOrder::factory()->status($status)->create(['order_id' => $order->id]);
}

/** Snapshot row pointing at a live variant (the post-checkout shape). */
function ordersItem(SubOrder $subOrder, ?Product $product = null, int $qty = 1): OrderItem
{
    $product ??= Product::factory()->create();
    $variant = $product->variants->first();

    return $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => $qty,
        'line_total_sen' => 2500 * $qty,
    ]);
}

test('orders page renders and guests are redirected to login', function () {
    $this->get(route('account.orders'))->assertRedirect(route('login'));

    $this->actingAs(ordersBuyer())->get(route('account.orders'))->assertOk();
});

test('tabs filter sub-orders into the right status groups', function () {
    $buyer = ordersBuyer();

    $confirmed = ordersSubOrder($buyer, SubOrderStatus::Confirmed);
    $processing = ordersSubOrder($buyer, SubOrderStatus::Processing);
    $shipped = ordersSubOrder($buyer, SubOrderStatus::Shipped);
    $delivered = ordersSubOrder($buyer, SubOrderStatus::Delivered);
    $completed = ordersSubOrder($buyer, SubOrderStatus::Completed);
    $cancelled = ordersSubOrder($buyer, SubOrderStatus::Cancelled);
    $refunded = ordersSubOrder($buyer, SubOrderStatus::Refunded);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)
        ->call('setTab', 'to-ship')
        ->assertSee($confirmed->sub_order_no)
        ->assertSee($processing->sub_order_no)
        ->assertDontSee($shipped->sub_order_no)
        ->assertDontSee($completed->sub_order_no)
        ->call('setTab', 'to-receive')
        ->assertSee($shipped->sub_order_no)
        ->assertSee($delivered->sub_order_no)
        ->assertDontSee($confirmed->sub_order_no)
        ->call('setTab', 'completed')
        ->assertSee($completed->sub_order_no)
        ->assertDontSee($cancelled->sub_order_no)
        ->call('setTab', 'cancelled')
        ->assertSee($cancelled->sub_order_no)
        ->assertDontSee($refunded->sub_order_no)
        ->call('setTab', 'return-refund')
        ->assertSee($refunded->sub_order_no)
        ->assertDontSee($completed->sub_order_no);
});

test('To Pay lists unpaid iPay88 orders with a countdown and hides paid, expired and COD orders', function () {
    $buyer = ordersBuyer();

    $awaiting = Order::factory()->awaitingIpay88(43)->create(['user_id' => $buyer->id]);
    SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $awaiting->id]);

    $expired = Order::factory()->create([
        'user_id' => $buyer->id,
        'payment_method' => PaymentMethod::Ipay88,
        'expires_at' => now()->subMinute(),
    ]);
    SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $expired->id]);

    $paid = Order::factory()->paid()->create(['user_id' => $buyer->id, 'payment_method' => PaymentMethod::Ipay88]);
    SubOrder::factory()->create(['order_id' => $paid->id]);

    $cod = Order::factory()->create(['user_id' => $buyer->id]); // COD orders confirm immediately — never "To Pay"
    SubOrder::factory()->create(['order_id' => $cod->id]);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)
        ->assertSet('tab', 'to-pay')
        ->assertSee($awaiting->order_no)
        ->assertSee('Expires in')
        ->assertSee('Pay now')
        ->assertSee('/pay/'.$awaiting->order_no, false)
        ->assertDontSee($expired->order_no)
        ->assertDontSee($paid->order_no)
        ->assertDontSee($cod->order_no);
});

test('cancelling an unpaid order cancels every sub-order, restocks, and records the reason', function () {
    $buyer = ordersBuyer();

    $order = Order::factory()->awaitingIpay88()->create(['user_id' => $buyer->id]);
    $subA = SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $order->id]);
    $subB = SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $order->id]);

    $product = Product::factory()->create();
    $variant = $product->variants->first();
    $variant->update(['stock' => 3]); // 2 units already reserved at checkout
    ordersItem($subA, $product, qty: 2);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)
        ->call('cancelUnpaidOrder', $order->id)
        ->assertDispatched('toast');

    expect($subA->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subB->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subA->fresh()->cancel_reason)->toBe('Cancelled before payment')
        ->and($variant->fresh()->stock)->toBe(5);

    expect($subA->statusHistories()->where([
        'from_status' => 'pending_payment',
        'to_status' => 'cancelled',
        'actor_type' => 'buyer',
        'actor_id' => $buyer->id,
        'note' => 'Cancelled before payment',
    ])->exists())->toBeTrue();
});

test("other buyers' orders are invisible and their detail pages 403", function () {
    $buyer = ordersBuyer();
    $stranger = ordersBuyer();

    $mine = ordersSubOrder($buyer, SubOrderStatus::Confirmed);
    $theirs = ordersSubOrder($stranger, SubOrderStatus::Confirmed);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)
        ->call('setTab', 'to-ship')
        ->assertSee($mine->sub_order_no)
        ->assertDontSee($theirs->sub_order_no);

    $this->get(route('account.orders.show', $mine))->assertOk();
    $this->get(route('account.orders.show', $theirs))->assertForbidden();
});

test('buyer cancels a confirmed sub-order with a reason — stock returns and history is written', function () {
    $buyer = ordersBuyer();
    $reason = CancellationReason::create(['label' => ['en' => 'Changed my mind'], 'is_active' => true, 'position' => 0]);

    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Confirmed);
    $product = Product::factory()->create();
    $variant = $product->variants->first();
    $variant->update(['stock' => 3]); // 2 units already reserved at checkout
    ordersItem($subOrder, $product, qty: 2);

    $this->actingAs($buyer);

    $component = Livewire::test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertSee('Cancel order')
        ->set('cancelling', true);

    // A reason is mandatory.
    $component->call('cancel')->assertHasErrors(['cancelReasonId' => 'required']);
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Confirmed);

    $component->set('cancelReasonId', $reason->id)
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $subOrder->refresh();

    expect($subOrder->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subOrder->cancel_reason)->toBe('Changed my mind')
        ->and($subOrder->cancelled_at)->not->toBeNull()
        ->and($variant->fresh()->stock)->toBe(5);

    expect($subOrder->statusHistories()->where([
        'from_status' => 'confirmed',
        'to_status' => 'cancelled',
        'actor_type' => 'buyer',
        'actor_id' => $buyer->id,
        'note' => 'Changed my mind',
    ])->exists())->toBeTrue();
});

test('cancel is not offered once the order has shipped', function () {
    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Shipped);
    ordersItem($subOrder);

    $this->actingAs($buyer);

    Livewire::test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertDontSee('Cancel order')
        ->assertSee('Waiting for delivery')
        ->call('cancel'); // guard refuses even if called directly

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('Order received completes a delivered sub-order', function () {
    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Delivered);
    ordersItem($subOrder);

    $this->actingAs($buyer);

    Livewire::test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertSee('Order received')
        ->call('confirmReceived')
        ->assertDispatched('toast');

    $subOrder->refresh();

    expect($subOrder->status)->toBe(SubOrderStatus::Completed)
        ->and($subOrder->completed_at)->not->toBeNull()
        ->and($subOrder->statusHistories()->where([
            'from_status' => 'delivered',
            'to_status' => 'completed',
            'actor_type' => 'buyer',
            'actor_id' => $buyer->id,
        ])->exists())->toBeTrue();
});

test('Order received is refused while the parcel is only shipped', function () {
    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Shipped);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)->call('confirmReceived', $subOrder->id);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('Buy again re-adds the first item variant to the cart', function () {
    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Completed);
    $product = Product::factory()->create();
    $product->variants->first()->update(['stock' => 5]);
    ordersItem($subOrder, $product);

    $this->actingAs($buyer);

    Livewire::test(Orders::class)
        ->call('setTab', 'completed')
        ->assertSee('Buy again')
        ->assertSee('Reviews arrive in M8')
        ->call('buyAgain', $subOrder->id)
        ->assertDispatched('toast');

    expect($buyer->cart->items()->where('product_variant_id', $product->variants->first()->id)->exists())->toBeTrue();
});

test('invoice downloads as a PDF for the owner and 403s for strangers', function () {
    Storage::fake('local');

    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Confirmed);
    ordersItem($subOrder);

    $this->actingAs($buyer)
        ->get(route('account.orders.invoice', $subOrder))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs(ordersBuyer())
        ->get(route('account.orders.invoice', $subOrder))
        ->assertForbidden();
});

test('detail page renders snapshots, timeline, address, totals and tracking', function () {
    $buyer = ordersBuyer();
    $subOrder = ordersSubOrder($buyer, SubOrderStatus::Shipped);
    $item = ordersItem($subOrder, qty: 2);
    $item->update(['product_name' => 'Snapshot name at purchase', 'variant_label' => 'Blue / XL']);

    $this->actingAs($buyer);

    Livewire::test(OrderDetail::class, ['subOrder' => $subOrder->fresh()])
        ->assertSee($subOrder->sub_order_no)
        ->assertSee('Snapshot name at purchase')
        ->assertSee('Blue / XL')
        ->assertSee($subOrder->order->shipping_address['recipient_name'])
        ->assertSee($subOrder->tracking_no)
        ->assertSee($subOrder->order->order_no)
        ->assertSee('Shipped');
});
