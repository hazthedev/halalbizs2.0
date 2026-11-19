<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Listeners\AwardCoinsOnCompletion;
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
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

/**
 * HB-G129 + HB-G130: a refund that lands BEFORE completion books (coins and the
 * seller's sale) must count at completion, so both match the money kept.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.earn_coins_per_rm' => 1]);
});

/**
 * iPay88 order: 2 × RM 100 + RM 5 shipping = total 20500, 5% commission (1000
 * on the 20000 items), walked to Delivered → ReturnRequested.
 *
 * @return array{0: User, 1: SubOrder}
 */
function returnRequestedSubOrder(PaymentMethod $method = PaymentMethod::Ipay88): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create();
    $product->store->forceFill(['commission_rate' => 5.0])->save();
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 500, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);
    $sub = app(CheckoutService::class)->place($buyer, $address, $method)->subOrders->first();

    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Confirmed, ActorType::System);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    $status->transition($sub->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer);

    expect($sub->fresh()->total_sen)->toBe(20_500);

    return [$buyer, $sub->fresh()];
}

function ledgerAmount(SubOrder $sub, LedgerEntryType $type): ?int
{
    return $sub->ledgerEntries()->where('type', $type)->value('amount_sen');
}

test('a partial refund before completion: coins only on the part kept', function () {
    [$buyer, $sub] = returnRequestedSubOrder();

    app(RefundService::class)->refund($sub, 10_250, ActorType::Admin, null); // half the total
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);

    // items 20000, half refunded → RM 100 kept → 100 coins (not 200)
    expect(app(CoinService::class)->balance($buyer))->toBe(100);
});

test('a partial refund before completion: the seller sale and commission are booked net', function () {
    [, $sub] = returnRequestedSubOrder();

    app(RefundService::class)->refund($sub, 10_250, ActorType::Admin, null);
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);

    // sale 20500 − 10250; commission 1000 scaled the way RefundService scales it
    expect(ledgerAmount($sub, LedgerEntryType::Sale))->toBe(10_250)
        ->and(ledgerAmount($sub, LedgerEntryType::Commission))->toBe(-500)
        ->and($sub->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->exists())->toBeFalse();
});

test('a later refund of the rest after completion takes every remaining coin and nets the seller to zero', function () {
    [$buyer, $sub] = returnRequestedSubOrder();
    $refunds = app(RefundService::class);

    $refunds->refund($sub, 10_250, ActorType::Admin, null);
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);
    $refunds->refund($sub->fresh(), 10_250, ActorType::Admin, null);

    expect(app(CoinService::class)->balance($buyer))->toBe(0)
        ->and((int) $sub->ledgerEntries()->sum('amount_sen'))->toBe(0);
});

test('a full refund before completion: completion books nothing and awards nothing', function () {
    [$buyer, $sub] = returnRequestedSubOrder();

    app(RefundService::class)->refund($sub, 20_500, ActorType::Admin, null, markRefunded: false);
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);

    expect(app(CoinService::class)->balance($buyer))->toBe(0)
        ->and($sub->ledgerEntries()->exists())->toBeFalse();
});

test('a refund that lands after completion but before the queued coin job runs is not paid out as coins', function () {
    [$buyer, $sub] = returnRequestedSubOrder();
    Queue::fake();

    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);
    app(RefundService::class)->refund($sub->fresh(), 10_250, ActorType::Admin, null); // no Earn row yet

    // The job carries the sub-order as it was at completion (no SerializesModels).
    $job = Queue::pushed(CallQueuedListener::class, fn ($job) => $job->class === AwardCoinsOnCompletion::class)->sole();
    $job = unserialize(serialize($job));
    app($job->class)->{$job->method}(...array_values($job->data));

    expect(app(CoinService::class)->balance($buyer))->toBe(100);

    app(RefundService::class)->refund($sub->fresh(), 10_250, ActorType::Admin, null);
    expect(app(CoinService::class)->balance($buyer))->toBe(0);
});

test('commission nets to zero across refunds that straddle completion (no rounding over-reversal)', function () {
    [, $sub] = returnRequestedSubOrder();
    $refunds = app(RefundService::class);

    // Commission 1000 on total 20500. Booked at completion: 1000 - round(1000 x 20434 / 20500 = 996.78) = 3.
    // Two later refunds of 33 sen each: rounding each alone gives round(1.61) = 2 twice = 4 reversed,
    // one more sen than was booked. Cumulative rounding reverses 1 then 2 = 3.
    $refunds->refund($sub, 20_434, ActorType::Admin, null);
    app(SubOrderStatusService::class)->transition($sub->fresh(), SubOrderStatus::Completed, ActorType::Admin);
    $refunds->refund($sub->fresh(), 33, ActorType::Admin, null);
    $refunds->refund($sub->fresh(), 33, ActorType::Admin, null);

    expect(ledgerAmount($sub, LedgerEntryType::Sale))->toBe(66)
        ->and(ledgerAmount($sub, LedgerEntryType::Commission))->toBe(-3)
        ->and((int) $sub->ledgerEntries()->sum('amount_sen'))->toBe(0);
});

test('COD: a partial refund before completion leaves the seller where the same refund after completion does', function () {
    $refunds = app(RefundService::class);
    $status = app(SubOrderStatusService::class);

    [, $before] = returnRequestedSubOrder(PaymentMethod::Cod);
    $refunds->refund($before, 10_250, ActorType::Admin, null);
    $status->transition($before->fresh(), SubOrderStatus::Completed, ActorType::Admin);

    [, $after] = returnRequestedSubOrder(PaymentMethod::Cod);
    $status->transition($after->fresh(), SubOrderStatus::Completed, ActorType::Admin);
    $refunds->refund($after->fresh(), 10_250, ActorType::Admin, null);

    // +sale -commission -cod_offset, less the refund with its commission share back:
    // -10250 - 1000 + 500 = -10750 either way.
    expect((int) $before->ledgerEntries()->sum('amount_sen'))->toBe(-10_750)
        ->and((int) $after->ledgerEntries()->sum('amount_sen'))->toBe(-10_750)
        ->and(ledgerAmount($before, LedgerEntryType::CodOffset))->toBe(-20_500);
});
