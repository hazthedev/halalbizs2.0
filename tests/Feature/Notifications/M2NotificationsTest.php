<?php

use App\Enums\ActorType;
use App\Enums\GroupBuyStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\SubscriptionInterval;
use App\Models\Address;
use App\Models\GroupBuy;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AffiliateCommissionNotification;
use App\Notifications\CoinsEarnedNotification;
use App\Notifications\GroupBuyUnlockedNotification;
use App\Notifications\SubscriptionOrderPlacedNotification;
use App\Services\AffiliateService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GroupBuyService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.earn_coins_per_rm' => 1, 'affiliate.enabled' => true, 'groupbuy.enabled' => true, 'subscriptions.enabled' => true]);
});

function m2NotifBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function m2NotifProduct(int $priceSen = 10_000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 20]);

    return $product;
}

function m2NotifComplete($sub, User $buyer): void
{
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);
}

test('completing an order notifies the buyer of coins earned', function () {
    Notification::fake();
    [$buyer, $address] = m2NotifBuyer();
    $product = m2NotifProduct();
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    m2NotifComplete($order->subOrders->first(), $buyer);

    Notification::assertSentTo($buyer, CoinsEarnedNotification::class);
});

test('a referred order completion notifies the creator of commission', function () {
    Notification::fake();
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    [$buyer, $address] = m2NotifBuyer();
    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);

    $product = m2NotifProduct();
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    m2NotifComplete($order->subOrders->first(), $buyer);

    Notification::assertSentTo($affiliate->user, AffiliateCommissionNotification::class);
});

test('a team unlocking notifies every joined member', function () {
    Notification::fake();
    $product = Product::factory()->create();
    $deal = GroupBuy::create([
        'store_id' => $product->store_id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => 6000,
        'target_size' => 2,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addWeek(),
    ]);

    $svc = app(GroupBuyService::class);
    $starter = User::factory()->create();
    $joiner = User::factory()->create();
    $team = $svc->startTeam($starter, $deal);
    $svc->joinTeam($joiner, $team);

    Notification::assertSentTo($starter, GroupBuyUnlockedNotification::class);
    Notification::assertSentTo($joiner, GroupBuyUnlockedNotification::class);
});

test('a processed subscription notifies the buyer their order was placed', function () {
    Notification::fake();
    [$buyer, $address] = m2NotifBuyer();
    $product = m2NotifProduct();
    $svc = app(SubscriptionService::class);
    $sub = $svc->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Weekly);
    $sub->update(['next_run_at' => now()->subMinute()]);

    $svc->processDue();

    Notification::assertSentTo($buyer, SubscriptionOrderPlacedNotification::class);
});
