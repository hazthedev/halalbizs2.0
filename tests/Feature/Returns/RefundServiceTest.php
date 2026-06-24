<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** A paid iPay88 sub-order walked all the way to completed (ledger booked). */
function refundCompletedSubOrder(bool $registered = false): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY']);

    $product = Product::factory()->create([
        'cod_enabled' => true,
        'tax_class' => $registered ? TaxClass::Standard : TaxClass::Exempt,
    ]);
    $product->store->update([
        'sst_registered' => $registered,
        'commission_rate' => 5.00,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 500,
        'free_shipping_over_sen' => null,
    ]);
    $product->variants->first()->update(['price_sen' => 20000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
    $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

    $subOrder = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
    $status->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id); // → Completed (ledger booked)

    // Then through the return flow so the sub-order is refundable.
    $status->transition($subOrder->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);
    $status->transition($subOrder->fresh(), SubOrderStatus::Returned, ActorType::Seller);

    return $subOrder->fresh();
}

test('a full refund reverses sale and commission exactly and settles the order', function () {
    $subOrder = refundCompletedSubOrder();
    $store = $subOrder->store;

    expect($store->availableBalanceSen())->toBe(19500); // +20500 sale −1000 commission

    app(RefundService::class)->refund($subOrder, $subOrder->total_sen, ActorType::Admin, null, 'IP88-RFND-1', markRefunded: true);

    $adjustment = $store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->sole();

    expect($adjustment->amount_sen)->toBe(-19500)
        ->and($adjustment->description)->toBe('Refund '.$subOrder->sub_order_no)
        ->and($store->availableBalanceSen())->toBe(0)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($subOrder->fresh()->order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($subOrder->fresh()->order->payment->refunded_sen)->toBe(20500)
        ->and($subOrder->fresh()->order->payment->refunded_at)->not->toBeNull();
});

test('a partial refund reverses proportionally and leaves the order open', function () {
    $subOrder = refundCompletedSubOrder();
    $store = $subOrder->store;

    // Refund RM100.00 of the RM205.00 sub-order, without closing it.
    app(RefundService::class)->refund($subOrder, 10000, ActorType::Admin, null, 'IP88-RFND-2', markRefunded: false);

    // commission reversal = round(1000 × 10000 / 20500) = 488; net = −10000 + 488.
    $adjustment = $store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->sole();

    expect($adjustment->amount_sen)->toBe(-9512)
        ->and($store->availableBalanceSen())->toBe(9988)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Returned) // not closed
        ->and($subOrder->fresh()->order->payment->refunded_sen)->toBe(10000);
});

test('a full refund for a registered seller reverses the tax too (nets to zero)', function () {
    $subOrder = refundCompletedSubOrder(registered: true);
    $store = $subOrder->store;

    // 20000 items + 500 shipping + 2000 SST = 22500 sale; commission 1000 → balance 21500.
    expect($store->availableBalanceSen())->toBe(21500)
        ->and($subOrder->tax_sen)->toBe(2000);

    app(RefundService::class)->refund($subOrder, $subOrder->total_sen, ActorType::Admin, null, 'IP88-RFND-3', markRefunded: true);

    expect($store->availableBalanceSen())->toBe(0); // tax included in the reversal
});

test('a refund before completion writes no ledger adjustment but tracks the payment', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    // Deliver (no completion → no Sale ledger entry), then request a return.
    $subOrder = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    $status->transition($subOrder->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);

    $total = (int) $subOrder->fresh()->total_sen;
    app(RefundService::class)->refund($subOrder->fresh(), $total, ActorType::Admin, null, null, markRefunded: true);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($subOrder->fresh()->ledgerEntries()->count())->toBe(0)
        ->and($subOrder->fresh()->order->payment->refunded_sen)->toBe($total);
});
