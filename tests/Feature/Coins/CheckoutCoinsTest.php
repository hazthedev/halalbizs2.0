<?php

use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\CoinTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.redemption_rate_sen' => 1, 'coins.earn_coins_per_rm' => 1]);
});

function coinsBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function noShippingProduct(int $priceSen = 10_000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);

    return $product;
}

test('checkout redeems coins, snapshots the value and reduces the grand total', function () {
    config(['coins.max_redemption_sen' => 5000]);
    [$buyer, $address] = coinsBuyer();
    app(CoinService::class)->credit($buyer, 2000, CoinTransactionType::Earn);

    $product = noShippingProduct(10_000); // RM100, no shipping, exempt tax
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, coinsToRedeem: 1500);

    expect($order->coin_redemption_sen)->toBe(1500)
        ->and($order->grand_total_sen)->toBe(8500)
        ->and($order->payment->amount_sen)->toBe(8500)
        ->and(app(CoinService::class)->balance($buyer))->toBe(500);

    $redeem = CoinTransaction::where('type', CoinTransactionType::Redeem)->first();
    expect($redeem->reference_id)->toBe($order->id)
        ->and($redeem->reference_type)->toBe($order->getMorphClass())
        ->and($redeem->sen)->toBe(1500);
});

test('coin redemption never zeroes the bill', function () {
    config(['coins.max_redemption_sen' => 1_000_000]);
    [$buyer, $address] = coinsBuyer();
    app(CoinService::class)->credit($buyer, 100_000, CoinTransactionType::Earn);

    $product = noShippingProduct(10_000);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, coinsToRedeem: 100_000);

    expect($order->grand_total_sen)->toBe(1)
        ->and($order->coin_redemption_sen)->toBe(9999);
});

test('passing zero coins keeps checkout identical (backward compatible)', function () {
    [$buyer, $address] = coinsBuyer();
    app(CoinService::class)->credit($buyer, 2000, CoinTransactionType::Earn);

    $product = noShippingProduct(10_000);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->coin_redemption_sen)->toBe(0)
        ->and($order->grand_total_sen)->toBe(10_000)
        ->and(app(CoinService::class)->balance($buyer))->toBe(2000);
});

test('coins are refunded when an unpaid order is fully cancelled', function () {
    config(['coins.max_redemption_sen' => 5000]);
    [$buyer, $address] = coinsBuyer();
    app(CoinService::class)->credit($buyer, 2000, CoinTransactionType::Earn);

    $product = noShippingProduct(10_000);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88, coinsToRedeem: 1500);
    expect(app(CoinService::class)->balance($buyer))->toBe(500);

    foreach ($order->subOrders as $sub) {
        app(OrderService::class)->cancel($sub, ActorType::System, null, 'Payment window closed');
    }

    expect(app(CoinService::class)->balance($buyer))->toBe(2000);
});

test('completing a sub-order earns coins for the buyer', function () {
    [$buyer, $address] = coinsBuyer();
    $product = noShippingProduct(10_000); // RM100 → 100 coins at 1/RM
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $sub = $order->subOrders->first();

    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    expect(app(CoinService::class)->balance($buyer))->toBe(100);

    // Re-completing must not double-earn (idempotent per sub-order).
    app(CoinService::class)->credit($buyer, 100, CoinTransactionType::Earn, $sub->fresh());
    expect(app(CoinService::class)->balance($buyer))->toBe(100);
});
