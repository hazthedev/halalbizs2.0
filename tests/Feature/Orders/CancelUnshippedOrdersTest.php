<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\SubOrderStatusNotification;
use App\Notifications\UnshippedOrderWarningNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * PAY-14 / HB-G143 (HB-DEC-1124d (2), Haze 2026-11-24 10:41): a sub-order not
 * shipped 7 days after the order was placed (online: after payment) is
 * cancelled through the normal cancel path; the seller is warned once on day
 * 5; the buyer is told; a paid online order is refunded in full.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

function unshippedSubOrder(PaymentMethod $method = PaymentMethod::Cod): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 500, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 10]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);
    $order = app(CheckoutService::class)->place($buyer, $address, $method);

    if ($method === PaymentMethod::Ipay88) {
        $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $order->payment->update(['status' => GatewayPaymentStatus::Success, 'paid_at' => now()]);
        app(SubOrderStatusService::class)->transition($order->subOrders->first(), SubOrderStatus::Confirmed, ActorType::System);
    }

    return $order->subOrders->first()->fresh();
}

function runUnshipped(): void
{
    test()->artisan('orders:cancel-unshipped')->assertSuccessful();
}

function stockOf(SubOrder $sub): int
{
    return (int) $sub->items->first()->variant->fresh()->stock;
}

test('day 5: the seller is warned once, the order stays open', function () {
    $sub = unshippedSubOrder();
    $seller = $sub->store->user;

    $this->travel(5 * 24 * 60 + 1)->minutes();
    runUnshipped();
    runUnshipped();

    Notification::assertSentToTimes($seller, UnshippedOrderWarningNotification::class, 1);
    expect($sub->fresh()->status)->toBe(SubOrderStatus::Confirmed);
});

test('not before day 5: no warning', function () {
    $sub = unshippedSubOrder();

    $this->travel(5 * 24 * 60 - 1)->minutes();
    runUnshipped();

    Notification::assertNotSentTo($sub->store->user, UnshippedOrderWarningNotification::class);
});

test('day 7: a COD sub-order is cancelled through the normal cancel path and both sides are told', function () {
    $sub = unshippedSubOrder();
    expect(stockOf($sub))->toBe(8);

    $this->travel(7 * 24 * 60 - 1)->minutes();
    runUnshipped();
    expect($sub->fresh()->status)->toBe(SubOrderStatus::Confirmed);

    $this->travel(2)->minutes();
    runUnshipped();
    runUnshipped(); // idempotent

    $fresh = $sub->fresh();
    expect($fresh->status)->toBe(SubOrderStatus::Cancelled)
        ->and($fresh->cancel_reason)->not->toBeNull()
        ->and(stockOf($sub))->toBe(10)
        ->and($fresh->statusHistories()->where('to_status', SubOrderStatus::Cancelled->value)->count())->toBe(1)
        ->and($fresh->order->payment->fresh()->refunded_sen ?? 0)->toBe(0); // COD: nothing was paid

    $told = fn (string $audience) => fn (SubOrderStatusNotification $n) => $n->status === SubOrderStatus::Cancelled && $n->audience === $audience;
    Notification::assertSentTo($fresh->order->user, SubOrderStatusNotification::class, $told('buyer'));
    Notification::assertSentTo($fresh->store->user, SubOrderStatusNotification::class, $told('seller'));
});

test('shipped on day 6: not cancelled', function () {
    $sub = unshippedSubOrder();
    $status = app(SubOrderStatusService::class);

    $this->travel(6)->days();
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);

    $this->travel(3)->days();
    runUnshipped();

    expect($sub->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('a packed (processing) sub-order still counts as not shipped', function () {
    $sub = unshippedSubOrder();
    app(SubOrderStatusService::class)->transition($sub, SubOrderStatus::Processing, ActorType::Seller);

    $this->travel(8)->days();
    runUnshipped();

    expect($sub->fresh()->status)->toBe(SubOrderStatus::Cancelled);
});

test('online paid: cancelled on day 7 after payment and refunded in full', function () {
    $sub = unshippedSubOrder(PaymentMethod::Ipay88);

    $this->travel(7 * 24 * 60 + 1)->minutes();
    runUnshipped();

    $fresh = $sub->fresh();
    expect($fresh->status)->toBe(SubOrderStatus::Cancelled)
        ->and((int) $fresh->refunded_sen)->toBe((int) $fresh->total_sen)
        ->and((int) $fresh->order->payment->fresh()->refunded_sen)->toBe((int) $fresh->order->grand_total_sen)
        ->and($fresh->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('race: a ship that lands first wins, the cancel steps aside cleanly', function () {
    $sub = unshippedSubOrder();
    $status = app(SubOrderStatusService::class);
    $this->travel(8)->days();

    $stale = $sub->fresh(); // the job read it as confirmed
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);

    expect(app(OrderService::class)->cancelIfUnshipped($stale, 'test'))->toBeFalse()
        ->and($sub->fresh()->status)->toBe(SubOrderStatus::Shipped)
        ->and(stockOf($sub))->toBe(8);
});

test('race: a cancel that lands first wins, the late ship is refused', function () {
    $sub = unshippedSubOrder();
    app(SubOrderStatusService::class)->transition($sub, SubOrderStatus::Processing, ActorType::Seller);
    $this->travel(8)->days();

    $stale = $sub->fresh(); // the seller's screen still shows processing
    expect(app(OrderService::class)->cancelIfUnshipped($sub->fresh(), 'test'))->toBeTrue();

    expect(fn () => app(SubOrderStatusService::class)->transition($stale, SubOrderStatus::Shipped, ActorType::Seller))
        ->toThrow(InvalidTransitionException::class);
    expect($sub->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and(stockOf($sub))->toBe(10);
});
