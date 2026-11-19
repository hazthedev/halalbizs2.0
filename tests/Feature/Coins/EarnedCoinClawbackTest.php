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

/** HB-G004: coins EARNED on a sub-order are taken back, pro rata, when it is refunded. */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.earn_coins_per_rm' => 1]);
});

function completedEarnOrder(int $priceSen = 10_000): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $sub = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod)->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    return [$buyer, $sub->fresh()];
}

test('a full refund takes back every coin earned on that sub-order', function () {
    [$buyer, $sub] = completedEarnOrder();
    expect(app(CoinService::class)->balance($buyer))->toBe(100); // RM 100 at 1 coin per RM

    app(RefundService::class)->refund($sub, $sub->total_sen, ActorType::Admin, null);

    expect(app(CoinService::class)->balance($buyer))->toBe(0);
});

test('a partial refund takes back the same share, and repeats never take more than was earned', function () {
    [$buyer, $sub] = completedEarnOrder();
    $refunds = app(RefundService::class);

    $refunds->refund($sub, intdiv($sub->total_sen, 2), ActorType::Admin, null);
    expect(app(CoinService::class)->balance($buyer))->toBe(50);

    $refunds->refund($sub->fresh(), $sub->total_sen, ActorType::Admin, null); // asks for more than is left
    expect(app(CoinService::class)->balance($buyer))->toBe(0);

    $refunds->refund($sub->fresh(), $sub->total_sen, ActorType::Admin, null); // nothing left: no-op
    expect(app(CoinService::class)->balance($buyer))->toBe(0);
});

test('coins already spent are not driven below zero', function () {
    [$buyer, $sub] = completedEarnOrder();
    app(CoinService::class)->adminAdjust($buyer, -80, 'spent elsewhere'); // only 20 left

    app(RefundService::class)->refund($sub, $sub->total_sen, ActorType::Admin, null);

    expect(app(CoinService::class)->balance($buyer))->toBe(0);
});

test('the take-back is on the coin history with the sub-order as its reference', function () {
    [$buyer, $sub] = completedEarnOrder();
    app(RefundService::class)->refund($sub, $sub->total_sen, ActorType::Admin, null);

    $row = app(CoinService::class)->walletFor($buyer)->transactions()
        ->where('type', CoinTransactionType::Adjustment)->where('reference_id', $sub->id)->sole();
    expect((int) $row->amount)->toBe(-100)->and($row->reference_type)->toBe($sub->getMorphClass());
});
