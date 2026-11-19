<?php

use App\Enums\ActorType;
use App\Enums\GroupBuyMemberStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\GroupBuyTeamStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\GroupBuyTeam;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GroupBuyService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SubOrderStatusService;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;

/*
 * HB-G003 (Haze 2026-11-19 "yes carry on wave 1"): the team price covers one
 * unit per member, and a member whose group-buy order is cancelled, or fully
 * refunded before it completes, gets the seat back (once).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['groupbuy.enabled' => true]);

    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->sandbox = true;
    $settings->save();
});

/** @return array{0: User, 1: Address, 2: GroupBuy, 3: GroupBuyTeam} */
function capSetup(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 20]);

    $deal = GroupBuy::create([
        'store_id' => $product->store_id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'group_price_sen' => 6000, 'target_size' => 2, 'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addWeek(),
    ]);
    $team = app(GroupBuyService::class)->startTeam($buyer, $deal);
    app(GroupBuyService::class)->joinTeam(User::factory()->create(), $team);
    expect($team->fresh()->status)->toBe(GroupBuyTeamStatus::Unlocked);

    return [$buyer, $address, $deal, $team];
}

function capMember(GroupBuyTeam $team, User $buyer): GroupBuyMember
{
    return GroupBuyMember::where('group_buy_team_id', $team->id)->where('user_id', $buyer->id)->firstOrFail();
}

/** A group-buy order the buyer has paid for (iPay88 settled, sub-order confirmed). */
function capPaidSubOrder(User $buyer, Address $address, GroupBuy $deal): SubOrder
{
    app(CartService::class)->addItem($buyer, $deal->variant->fresh(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
    $order->update(['payment_status' => PaymentStatus::Paid]);
    $subOrder = $order->subOrders->first();
    app(SubOrderStatusService::class)->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);

    return $subOrder->fresh();
}

test('web checkout refuses more than one unit at the team price', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    app(CartService::class)->addItem($buyer, $deal->variant, 2);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'The group-buy price covers 1 unit per member');

    expect(Order::count())->toBe(0)
        ->and(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Joined)
        ->and(app(CheckoutService::class)->quote($buyer, $address)->blockReason)->toContain('1 unit per member');
});

test('app API checkout refuses more than one unit at the team price', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    app(CartService::class)->addItem($buyer, $deal->variant, 3);

    app('auth')->forgetGuards();
    $this->withToken($buyer->createToken('pest-phone')->plainTextToken)
        ->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'checkout_failed');

    expect(Order::count())->toBe(0)
        ->and(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Joined);
});

test('one unit at the team price still goes through', function () {
    [$buyer, $address, $deal] = capSetup();
    app(CartService::class)->addItem($buyer, $deal->variant, 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->subOrders->first()->items->first()->unit_price_sen)->toBe(6000);
});

test('a cancel after payment frees the seat once; a second cancel frees none', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    $first = capPaidSubOrder($buyer, $address, $deal);

    app(OrderService::class)->cancel($first, ActorType::Buyer, $buyer->id);

    $member = capMember($team, $buyer);
    expect($member->status)->toBe(GroupBuyMemberStatus::Joined)
        ->and($member->sub_order_id)->toBeNull();

    // The seat is used again on a new order; replaying the first cancel must
    // not free the seat the new order holds.
    $second = capPaidSubOrder($buyer, $address, $deal);
    expect($second->items->first()->unit_price_sen)->toBe(6000);

    app(OrderService::class)->cancel($first->fresh(), ActorType::Buyer, $buyer->id);

    expect(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Purchased)
        ->and(capMember($team, $buyer)->sub_order_id)->toBe($second->id);
});

test('a full refund before completion frees the seat', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    $subOrder = capPaidSubOrder($buyer, $address, $deal);
    $status = app(SubOrderStatusService::class);

    foreach ([SubOrderStatus::Processing, SubOrderStatus::Shipped, SubOrderStatus::Delivered, SubOrderStatus::ReturnRequested] as $to) {
        $status->transition($subOrder->fresh(), $to, ActorType::System);
    }

    app(RefundService::class)->refund($subOrder->fresh(), (int) $subOrder->total_sen, ActorType::Admin, null, 'REF1', attemptGatewayRefund: false);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Joined);
});

test('a partial refund keeps the seat used', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    $subOrder = capPaidSubOrder($buyer, $address, $deal);

    app(RefundService::class)->refund($subOrder, 1000, ActorType::Admin, null, 'REF1', attemptGatewayRefund: false);

    expect(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Purchased);
});

test('a cancel never reopens a team that unlocked, and a closed deal stays closed', function () {
    [$buyer, $address, $deal, $team] = capSetup();
    $subOrder = capPaidSubOrder($buyer, $address, $deal);
    $completedAt = $team->fresh()->completed_at;

    $deal->update(['ends_at' => now()->subMinute()]); // deal closed after the purchase
    app(OrderService::class)->cancel($subOrder, ActorType::Seller, null);

    $team = $team->fresh();
    expect(capMember($team, $buyer)->status)->toBe(GroupBuyMemberStatus::Joined)
        ->and($team->status)->toBe(GroupBuyTeamStatus::Unlocked)
        ->and($team->completed_at->equalTo($completedAt))->toBeTrue()
        ->and($team->members()->count())->toBe(2);

    // The seat is back, but the closed deal does not price at the team price.
    app(CartService::class)->addItem($buyer, $deal->variant->fresh(), 1);
    $again = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    expect($again->subOrders->first()->items->first()->unit_price_sen)->toBe(10_000);
});
