<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
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
    $product->store->forceFill(['commission_rate' => 5.00])->save();
    $product->store->update([
        'sst_registered' => $registered,
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

test('a repeated full refund is idempotent — it never refunds or reverses twice', function () {
    $subOrder = refundCompletedSubOrder();
    $store = $subOrder->store;

    // Two identical full-refund calls (admin double-click / racing callback).
    app(RefundService::class)->refund($subOrder->fresh(), $subOrder->total_sen, ActorType::Admin, null, 'IP88-DUP', markRefunded: true);
    app(RefundService::class)->refund($subOrder->fresh(), $subOrder->total_sen, ActorType::Admin, null, 'IP88-DUP', markRefunded: true);

    expect($subOrder->fresh()->order->payment->refunded_sen)->toBe(20500) // once — not 41000
        ->and($subOrder->fresh()->refunded_sen)->toBe(20500)
        ->and($store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->count())->toBe(1)
        ->and($store->availableBalanceSen())->toBe(0); // reversed once, not driven negative
});

test('a partial refund never flips the sub-order to terminal Refunded, even with markRefunded set', function () {
    $subOrder = refundCompletedSubOrder(); // total_sen = 20500, currently Returned

    // The admin Returns flow passes markRefunded:true with a line-item (partial) amount.
    app(RefundService::class)->refund($subOrder->fresh(), 10000, ActorType::Admin, null, 'IP88-PART', markRefunded: true);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Returned) // NOT terminally Refunded
        ->and($subOrder->fresh()->refunded_sen)->toBe(10000)
        ->and($subOrder->fresh()->order->payment->refunded_sen)->toBe(10000);

    // The remaining balance is still refundable (not trapped in a terminal state).
    app(RefundService::class)->refund($subOrder->fresh(), 10500, ActorType::Admin, null, 'IP88-PART2', markRefunded: true);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Refunded) // now genuinely full
        ->and($subOrder->fresh()->refunded_sen)->toBe(20500)
        ->and($subOrder->fresh()->order->payment->refunded_sen)->toBe(20500);
});

test('refunding every sub-order of a voucher order never returns more cash than was charged', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY']);

    $makeProduct = function () {
        $product = Product::factory()->create(['cod_enabled' => true, 'tax_class' => TaxClass::Exempt]);
        $product->store->forceFill(['commission_rate' => 5.00])->save();
        $product->store->update([
            'sst_registered' => false,
            'shipping_mode' => 'flat',
            'shipping_flat_fee_sen' => 0,
            'free_shipping_over_sen' => null,
        ]);
        $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);

        return $product;
    };

    // Two stores → two sub-orders, each total 10000 (Σ = 20000).
    $p1 = $makeProduct();
    $p2 = $makeProduct();
    app(CartService::class)->addItem($buyer, $p1->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $p2->variants->first(), 1);

    // A 25% platform voucher lowers grand_total to 15000 — below Σ sub-order totals.
    Voucher::create([
        'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => 'PLAT25',
        'type' => VoucherType::Percent, 'percent' => 25, 'value_sen' => 0, 'min_spend_sen' => 0,
        'quota' => null, 'per_user_limit' => 5, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88, 'PLAT25');
    $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

    expect($order->grand_total_sen)->toBe(15000); // 20000 − 5000 platform discount

    // Complete both sub-orders (books the seller ledger), then fully refund each.
    $status = app(SubOrderStatusService::class);
    foreach ($order->subOrders as $subOrder) {
        $status->transition($subOrder->fresh(), SubOrderStatus::Confirmed, ActorType::System);
        $status->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
        $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
        app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
        app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);
        app(RefundService::class)->refund($subOrder->fresh(), (int) $subOrder->fresh()->total_sen, ActorType::Admin, null, 'RF-'.$subOrder->id, markRefunded: true);
    }

    // The buyer is refunded exactly what they paid — never the (larger) sum of
    // the sub-order totals, which would refund the platform's voucher subsidy.
    expect($order->fresh()->payment->refunded_sen)->toBe(15000)
        ->and($order->fresh()->payment->refunded_sen)->toBeLessThanOrEqual($order->fresh()->grand_total_sen);
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

/** Two identical stores, one product each, flat shipping $shippingSen per store. */
function twoStoreOrder(User $buyer, Address $address, int $shippingSen = 0, int $coins = 0, ?string $code = null): App\Models\Order
{
    foreach ([1, 2] as $ignored) {
        $product = Product::factory()->create(['cod_enabled' => true, 'tax_class' => TaxClass::Exempt]);
        $product->store->forceFill(['commission_rate' => 5.00])->save();
        $product->store->update([
            'sst_registered' => false,
            'shipping_mode' => 'flat',
            'shipping_flat_fee_sen' => $shippingSen,
            'free_shipping_over_sen' => null,
        ]);
        $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);
        app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    }

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88, $code, coinsToRedeem: $coins);
    $order->forceFill(['payment_status' => App\Enums\PaymentStatus::Paid, 'paid_at' => now()])->save();

    return $order;
}

function completeAndRefund(SubOrder $subOrder, User $buyer): void
{
    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder->fresh(), SubOrderStatus::Confirmed, ActorType::System);
    $status->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);
    app(RefundService::class)->refund($subOrder->fresh(), (int) $subOrder->fresh()->total_sen, ActorType::Admin, null, 'RF-'.$subOrder->id, markRefunded: true);
}

// H-4: the order-level `grand_total − refunded` check is a cumulative CEILING,
// not an allocation. Refund the FIRST store of a multi-store order and it fits
// under that ceiling easily, so the buyer got back cash they never paid and the
// last store would have come up short. C11 fixed this for the platform voucher;
// coins and platform-funded shipping were left out of the same sum.
test('coins come out of the refunded cash — the first store refunded gets its share, not its total', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY']);

    // The audit's worked example: 2 × RM100, no vouchers, RM20 of coins.
    app(App\Services\CoinService::class)->credit($buyer, 2000, App\Enums\CoinTransactionType::Earn);
    $order = twoStoreOrder($buyer, $address, coins: 2000);

    expect($order->coin_redemption_sen)->toBe(2000)
        ->and($order->grand_total_sen)->toBe(18000);            // 20000 − RM20 coins

    completeAndRefund($order->subOrders->first(), $buyer);

    // RM90, not the RM100 sub-order total: the buyer paid RM90 cash toward it.
    expect((int) $order->fresh()->payment->refunded_sen)->toBe(9000);
});

test('platform-funded free shipping stays with the seller but is not refunded as cash', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY']);

    Voucher::create([
        'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => 'PLATSHIP',
        'type' => VoucherType::FreeShipping, 'percent' => 0, 'value_sen' => 0, 'min_spend_sen' => 0,
        'quota' => null, 'per_user_limit' => 5, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    $order = twoStoreOrder($buyer, $address, shippingSen: 500, code: 'PLATSHIP');
    $first = $order->subOrders->first();

    // The fee stays ON the sub-order (the seller still earns it) while the buyer
    // is not charged — which is precisely why total_sen overstates cash paid.
    expect((int) $first->shipping_subsidy_sen)->toBeGreaterThan(0)
        ->and((int) $first->shipping_fee_sen)->toBe((int) $first->shipping_subsidy_sen)
        ->and((int) $order->grand_total_sen)->toBeLessThan((int) $order->subOrders->sum('total_sen'));

    completeAndRefund($first, $buyer);

    expect((int) $order->fresh()->payment->refunded_sen)
        ->toBe((int) $first->total_sen - (int) $first->shipping_subsidy_sen);
});
