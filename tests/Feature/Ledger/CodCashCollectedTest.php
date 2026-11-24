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
