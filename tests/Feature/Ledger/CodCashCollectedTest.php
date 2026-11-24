<?php

use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

/**
 * HB-DEC-1124d (Haze 2026-11-24 10:41):
 * PAY-09 / HB-G026: a COD seller is charged only on the cash they collected;
 * the platform covers its own vouchers, coins and platform free shipping.
 * PAY-12 / HB-G027: on a full COD refund the platform pays the buyer back and
 * the seller owes it (ruling 2026-11-03 15:58 (6)).
 *
 * Fixture: COD, 2 × RM 100 + RM 5 shipping = sub-order total 20500, 5%
 * commission = 1000 on the 20000 items.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.redemption_rate_sen' => 1, 'coins.max_redemption_sen' => 5000]);
});

function codDelivered(?string $platformCode = null, int $coins = 0, ?callable $withStore = null): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->forceFill(['commission_rate' => 5.0])->save();
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 500, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 10]);
    $withStore && $withStore($product->store);

    if ($coins > 0) {
        app(CoinService::class)->credit($buyer, $coins, CoinTransactionType::Earn);
    }

    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);
    $sub = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, $platformCode, coinsToRedeem: $coins)->subOrders->first();

    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);

    expect($sub->fresh()->total_sen)->toBe(20_500);

    return $sub->fresh();
}

function codComplete(SubOrder $sub): SubOrder
{
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Buyer);

    return $sub->fresh();
}

function platformVoucher(string $code, int $valueSen = 2000): void
{
    Voucher::create([
        'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => $code,
        'type' => VoucherType::Fixed, 'value_sen' => $valueSen, 'percent' => null,
        'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

function codNet(SubOrder $sub): int
{
    return (int) $sub->ledgerEntries()->sum('amount_sen');
}

function codOffset(SubOrder $sub): ?int
{
    return $sub->ledgerEntries()->where('type', LedgerEntryType::CodOffset)->value('amount_sen');
}

test('PAY-09: a platform voucher on COD is credited to the seller, the offset is the cash collected', function () {
    platformVoucher('PLAT20');
    $sub = codComplete(codDelivered('PLAT20'));

    // The buyer handed the courier 20500 - 2000 = 18500. Sale 20500, commission
    // -1000, offset -18500: the platform owes the seller its RM 20 less RM 10.
    expect((int) $sub->order->grand_total_sen)->toBe(18_500)
        ->and(codOffset($sub))->toBe(-18_500)
        ->and(codNet($sub))->toBe(1_000);
});

test('PAY-09: coins on COD are the platform\'s cost, not the seller\'s', function () {
    $sub = codComplete(codDelivered(coins: 1500));

    expect((int) $sub->order->grand_total_sen)->toBe(19_000)
        ->and(codOffset($sub))->toBe(-19_000)
        ->and(codNet($sub))->toBe(500);
});

test('PAY-09: with no platform discount the COD seller still owes only the commission', function () {
    $sub = codComplete(codDelivered());

    expect(codOffset($sub))->toBe(-20_500)
        ->and(codNet($sub))->toBe(-1_000);
});

test('PAY-12: full COD refund after completion: the platform pays the buyer the cash, the seller owes that cash', function () {
    platformVoucher('PLAT20B');
    $sub = codComplete(codDelivered('PLAT20B'));

    app(RefundService::class)->refund($sub, 20_500, ActorType::Admin, null);

    // The seller kept 18500 cash from the courier; the platform paid 18500 back
    // to the buyer; the seller owes exactly that, not the 20500 sale.
    expect((int) $sub->order->payment->fresh()->refunded_sen)->toBe(18_500)
        ->and(codNet($sub))->toBe(-18_500);
});

test('PAY-12: full COD refund before completion (return on a delivered order): the seller owes the cash collected', function () {
    platformVoucher('PLAT20C');
    $sub = codDelivered('PLAT20C');
    app(SubOrderStatusService::class)->transition($sub, SubOrderStatus::ReturnRequested, ActorType::Buyer);

    app(RefundService::class)->refund($sub->fresh(), 20_500, ActorType::Admin, null);

    expect($sub->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and((int) $sub->order->payment->fresh()->refunded_sen)->toBe(18_500)
        ->and(codOffset($sub))->toBe(-18_500)
        ->and(codNet($sub))->toBe(-18_500);

    // Idempotent: a repeat refund books nothing more.
    app(RefundService::class)->refund($sub->fresh(), 20_500, ActorType::Admin, null);
    expect(codNet($sub))->toBe(-18_500);
});

/*
| Gate HB-B4 (PR #274): the three platform-funded reductions together, a
| two-store split, and refund/completion ordering. Expected numbers are
| hand-computed in the comments, not read back from the code.
*/

function platformFreeShipping(string $code): void
{
    Voucher::create([
        'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => $code,
        'type' => VoucherType::FreeShipping, 'value_sen' => 0, 'percent' => 0,
        'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

/** @param  array<int, array{0: int, 1: int, 2: int}>  $stores  [price_sen, qty, flat shipping_sen] */
function codMultiDelivered(array $stores, ?string $platformCode = null, ?string $shippingCode = null, int $coins = 0): \App\Models\Order
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    foreach ($stores as [$priceSen, $qty, $shippingSen]) {
        $product = Product::factory()->create(['cod_enabled' => true]);
        $product->store->forceFill(['commission_rate' => 5.0])->save();
        $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => $shippingSen, 'free_shipping_over_sen' => null]);
        $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);
        app(CartService::class)->addItem($buyer, $product->variants->first(), $qty);
    }

    if ($coins > 0) {
        app(CoinService::class)->credit($buyer, $coins, CoinTransactionType::Earn);
    }

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, $platformCode, shippingCode: $shippingCode, coinsToRedeem: $coins);

    $status = app(SubOrderStatusService::class);
    foreach ($order->subOrders as $sub) {
        $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
        $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
        app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    }

    return $order->fresh();
}

test('gate: COD with platform voucher + coins + platform free shipping together: offset is the cash collected', function () {
    platformVoucher('GPLAT20');
    platformFreeShipping('GSHIP');

    // total 20000 + 500 = 20500; voucher 2000, free shipping 500, coins 1500:
    // cash = 20500 - 2000 - 500 - 1500 = 16500. Net = 20500 - 1000 - 16500 = 3000.
    $sub = codComplete(codMultiDelivered([[10_000, 2, 500]], 'GPLAT20', 'GSHIP', 1500)->subOrders->first());

    expect((int) $sub->total_sen)->toBe(20_500)
        ->and((int) $sub->shipping_subsidy_sen)->toBe(500)
        ->and((int) $sub->order->grand_total_sen)->toBe(16_500)
        ->and(codOffset($sub))->toBe(-16_500)
        ->and(codNet($sub))->toBe(3_000);
});

test('gate: two-store COD split: offsets add up to the cash the buyer paid, no sen lost or doubled', function () {
    platformVoucher('GPLAT10', 1000);

    // A 20000 + 500 ship, B 3333 + 700 ship. Voucher 1000 on subtotals
    // 20000:3333 -> 857 r3619 / 142 r19714, leftover sen to B -> 857 / 143.
    // Bases 19643 / 3890 (sum 23533). Coins 777 -> 648 r13227 / 128 r10306,
    // leftover to A -> 649 / 128. Cash A 18994, B 3762 = 22756 = grand total.
    // Commission 5%: A 1000, B intdiv(3333*500+5000, 10000) = 167.
    $order = codMultiDelivered([[10_000, 2, 500], [3_333, 1, 700]], 'GPLAT10', null, 777);
    [$a, $b] = $order->subOrders->sortBy('id')->values()->map(fn (SubOrder $s) => codComplete($s))->all();

    expect((int) $order->grand_total_sen)->toBe(22_756)
        ->and(codOffset($a))->toBe(-18_994)
        ->and(codOffset($b))->toBe(-3_762)
        ->and(codOffset($a) + codOffset($b))->toBe(-(int) $order->grand_total_sen)
        ->and(codNet($a))->toBe(506)
        ->and(codNet($b))->toBe(104);
});

test('gate: COD partial refund before completion, completion, then the rest: same end as one full refund, no double charge', function () {
    platformVoucher('GPLAT20P');
    $sub = codDelivered('GPLAT20P');

    app(RefundService::class)->refund($sub->fresh(), 10_250, ActorType::Admin, null);
    expect($sub->ledgerEntries()->count())->toBe(0);

    // Sale 20500 - 10250, commission 1000 - 500, offset -18500 (full cash).
    $sub = codComplete($sub);
    expect(codOffset($sub))->toBe(-18_500)
        ->and(codNet($sub))->toBe(10_250 - 500 - 18_500);

    app(RefundService::class)->refund($sub->fresh(), 10_250, ActorType::Admin, null);
    expect(codNet($sub))->toBe(-18_500);
});

test('gate: full COD refund before completion, then a completion hook: nothing booked twice', function () {
    platformVoucher('GPLAT20D');
    $sub = codDelivered('GPLAT20D');
    app(SubOrderStatusService::class)->transition($sub, SubOrderStatus::ReturnRequested, ActorType::Buyer);
    app(RefundService::class)->refund($sub->fresh(), 20_500, ActorType::Admin, null);

    app(\App\Services\LedgerService::class)->recordCompletion($sub->fresh());

    expect($sub->ledgerEntries()->count())->toBe(1)
        ->and(codNet($sub))->toBe(-18_500);
});
