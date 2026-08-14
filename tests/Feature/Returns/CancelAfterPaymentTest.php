<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Orders\Payments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// Audit H-3. For iPay88 a sub-order only reaches Confirmed after the money
// settles, and Confirmed is exactly what the buyer's cancel button allows — so
// "changed my mind after paying" is the ordinary path, not an edge case.
//
// Before the fix cancel() did restock() and refundCoinsIfFullyCancelled() and
// nothing else. The goods went back on the shelf, the platform kept the cash,
// payment_status still read Paid and refunded_sen stayed 0 on both rows, so the
// debt showed up on no reconciliation surface anywhere in the app.
//
// Fixture shape follows tests/Feature/Admin/OrdersOversightTest.php — a paid
// iPay88 order with a real Payment row, sitting at Confirmed.

// Local, per the convention in this suite: shared setup is a plain top-of-file
// helper, one per file. Borrowing OrdersOversightTest's oversightAdmin() would
// work today only because Pest loads every test file, and would break the
// moment load order changed.
function cancelRefundAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // EnsureAdmin requires 2FA
    makeAdmin($user);

    return $user;
}

/** @return array{0: SubOrder, 1: Payment} */
function paidConfirmedSubOrder(int $totalSen = 10500, array $orderAttributes = []): array
{
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'subtotal_sen' => $totalSen,
        'grand_total_sen' => $totalSen,
        'coin_redemption_sen' => 0,
        'discount_total_sen' => 0,
        ...$orderAttributes,
    ]);

    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants->first();

    $subOrder = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'items_subtotal_sen' => $totalSen,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'total_sen' => $totalSen,
        'commission_rate' => '5.00',
    ]);

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => $variant->options_label,
        'unit_price_sen' => $totalSen,
        'qty' => 1,
        'line_total_sen' => $totalSen,
    ]);

    // No PaymentFactory exists — build it the way RefundLedgerStressTest does.
    $payment = Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $order->order_no,
        'amount_sen' => $totalSen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0,
        'paid_at' => now(),
    ]);

    return [$subOrder, $payment];
}

test('cancelling a PAID sub-order returns the money', function () {
    [$subOrder, $payment] = paidConfirmedSubOrder();

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $subOrder->order->user_id, 'Changed my mind');

    expect($subOrder->fresh()->refunded_sen)->toBe(10500)
        ->and($payment->fresh()->refunded_sen)->toBe(10500)
        ->and($payment->fresh()->refunded_at)->not->toBeNull();
});

test('the order stops reporting itself as Paid once its last sub-order settles', function () {
    [$subOrder] = paidConfirmedSubOrder();

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $subOrder->order->user_id);

    // The lie this finding is about: refunded_sen == total_sen while
    // payment_status still reads Paid, so the cash owed is invisible.
    expect($subOrder->order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('the sub-order stays Cancelled — it was never returned', function () {
    [$subOrder] = paidConfirmedSubOrder();

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $subOrder->order->user_id);

    // markRefunded: false is the point. The goods were restocked and 'cancelled'
    // is terminal by design; we want the money engine, not the status change.
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled);
});

test('a second cancel does not refund twice', function () {
    [$subOrder, $payment] = paidConfirmedSubOrder();

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $subOrder->order->user_id);

    // Cancelled -> Cancelled is a no-op in the transition service rather than a
    // throw, so the $after closure can genuinely run twice on a double-click.
    app(OrderService::class)->cancel($subOrder->fresh(), ActorType::Buyer, $subOrder->order->user_id);

    expect($subOrder->fresh()->refunded_sen)->toBe(10500)
        ->and($payment->fresh()->refunded_sen)->toBe(10500);
});

test('an UNPAID cancel still refunds nothing', function () {
    [$subOrder, $payment] = paidConfirmedSubOrder();
    $subOrder->order->forceFill(['payment_status' => PaymentStatus::Pending, 'paid_at' => null])->save();

    app(OrderService::class)->cancel($subOrder->fresh(), ActorType::Buyer, $subOrder->order->user_id);

    expect($subOrder->fresh()->refunded_sen)->toBe(0)
        ->and($payment->fresh()->refunded_sen)->toBe(0)
        ->and($subOrder->order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('one store cancelling does not settle a two-store order', function () {
    [$subOrderA] = paidConfirmedSubOrder(10000);
    $order = $subOrderA->order;

    $storeB = Store::factory()->approved()->create();
    SubOrder::factory()->status(SubOrderStatus::Confirmed)->create([
        'order_id' => $order->id,
        'store_id' => $storeB->id,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'total_sen' => 10000,
        'commission_rate' => '5.00',
    ]);
    $order->forceFill(['subtotal_sen' => 20000, 'grand_total_sen' => 20000])->save();

    app(OrderService::class)->cancel($subOrderA->fresh(), ActorType::Buyer, $order->user_id);

    // Store A's cash comes back, but store B is still live, so the ORDER has not
    // been refunded and must not say it has.
    expect($subOrderA->fresh()->refunded_sen)->toBe(10000)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

// H-3's other half: the money moving is only useful if a human can see it.
// refunded_sen was written by RefundService and read by NOTHING — zero hits in
// resources/views — so a refund appeared on no reconciliation surface at all.
// Asserting on the rendered grid, because the defect was the absence of a
// rendered surface, not the absence of a column.
test('the admin payments grid shows cash owed back', function () {
    $admin = cancelRefundAdmin();
    [$subOrder, $payment] = paidConfirmedSubOrder();

    Livewire::actingAs($admin)->test(Payments::class)
        ->assertSee(__('Refunded'))
        ->assertSee($payment->ref_no)
        ->assertDontSee('−RM 105.00');

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $subOrder->order->user_id);

    Livewire::actingAs($admin)->test(Payments::class)
        ->assertSee('−RM 105.00');
});
