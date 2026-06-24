<?php

use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // Zero the earn-on-completion rate so these tests isolate refund reversal
    // (completing an order otherwise credits earned coins — covered elsewhere).
    config([
        'coins.enabled' => true,
        'coins.redemption_rate_sen' => 1,
        'coins.max_redemption_sen' => 100_000,
        'coins.earn_coins_per_rm' => 0,
    ]);
});

function refundCoinBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function refundCoinProduct(int $priceSen = 10_000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);

    return $product;
}

function placeCoinOrder(User $buyer, Address $address, int $coins): SubOrder
{
    app(CoinService::class)->credit($buyer, $coins, CoinTransactionType::Earn);
    $product = refundCoinProduct(10_000);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, coinsToRedeem: $coins);

    $sub = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    return $sub->fresh();
}

test('a full refund returns all redeemed coins', function () {
    [$buyer, $address] = refundCoinBuyer();
    $sub = placeCoinOrder($buyer, $address, 2000);
    expect($sub->order->coin_redemption_sen)->toBe(2000)
        ->and(app(CoinService::class)->balance($buyer))->toBe(0);

    app(RefundService::class)->refund($sub, $sub->total_sen, ActorType::Admin, null);

    expect(app(CoinService::class)->balance($buyer))->toBe(2000);
});

test('a partial refund returns a proportional share of coins', function () {
    [$buyer, $address] = refundCoinBuyer();
    $sub = placeCoinOrder($buyer, $address, 2000); // basis = grand(8000) + coins(2000) = 10000

    // Refund half the bill → half the coins (markRefunded:false keeps it open).
    app(RefundService::class)->refund($sub, 5000, ActorType::Admin, null, null, false);

    expect(app(CoinService::class)->balance($buyer))->toBe(1000);
});

test('cumulative reversals never exceed the coins originally redeemed', function () {
    [$buyer, $address] = refundCoinBuyer();
    $sub = placeCoinOrder($buyer, $address, 2000);
    $refund = app(RefundService::class);

    $refund->refund($sub->fresh(), 5000, ActorType::Admin, null, null, false); // +1000
    $refund->refund($sub->fresh(), 5000, ActorType::Admin, null, null, false); // +1000 (total 2000)
    $refund->refund($sub->fresh(), 5000, ActorType::Admin, null, null, false); // capped → +0

    expect(app(CoinService::class)->balance($buyer))->toBe(2000);
});

test('an order with no coin redemption is unaffected by a refund', function () {
    [$buyer, $address] = refundCoinBuyer();
    $sub = placeCoinOrder($buyer, $address, 0); // no coins redeemed

    app(RefundService::class)->refund($sub, $sub->total_sen, ActorType::Admin, null);

    expect(app(CoinService::class)->balance($buyer))->toBe(0);
});
