<?php

use App\Enums\ActorType;
use App\Enums\GroupBuyMemberStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\GroupBuyTeamStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Address;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GroupBuyService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
 * HB-VOUCHER-D1 (Haze 2026-11-17 12:15 "yes"): an order cancelled before
 * payment, or expired unpaid, gives back the voucher quota, the buyer's
 * per-user use and the group-buy seat, in the same cancel transaction.
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

function entVoucher(string $code, VoucherScope $scope, ?int $storeId = null): Voucher
{
    return Voucher::create([
        'code' => $code, 'scope' => $scope, 'store_id' => $storeId,
        'type' => VoucherType::Fixed, 'value_sen' => 500, 'min_spend_sen' => 0,
        'quota' => 1, 'per_user_limit' => 1, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

/** @return array{0: Order, 1: User, 2: Address, 3: Voucher, 4: Voucher, 5: GroupBuyMember, 6: GroupBuy} */
function entOrder(PaymentMethod $method = PaymentMethod::Ipay88): array
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

    $platform = entVoucher('PLAT5', VoucherScope::Platform);
    $shop = entVoucher('SHOP5', VoucherScope::Shop, $product->store_id);

    app(CartService::class)->addItem($buyer, $variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, $method, 'PLAT5', 'SHOP5');

    $member = GroupBuyMember::where('group_buy_team_id', $team->id)->where('user_id', $buyer->id)->first();

    // Consumed at checkout, as the gate's probe saw.
    expect($platform->fresh()->used_count)->toBe(1)
        ->and($shop->fresh()->used_count)->toBe(1)
        ->and(VoucherUsage::where('user_id', $buyer->id)->count())->toBe(2)
        ->and($member->status)->toBe(GroupBuyMemberStatus::Purchased);

    return [$order, $buyer, $address, $platform, $shop, $member, $deal];
}

function expectReleased(User $buyer, Voucher $platform, Voucher $shop, GroupBuyMember $member): void
{
    expect($platform->fresh()->used_count)->toBe(0)
        ->and($shop->fresh()->used_count)->toBe(0)
        ->and(VoucherUsage::where('user_id', $buyer->id)->count())->toBe(0)
        ->and($member->fresh()->status)->toBe(GroupBuyMemberStatus::Joined)
        ->and($member->fresh()->sub_order_id)->toBeNull();
}

test('buyer cancel before payment gives back voucher quota, per-user use and the group-buy seat', function () {
    [$order, $buyer, $address, $platform, $shop, $member, $deal] = entOrder();

    app(OrderService::class)->cancelUnpaid($order, ActorType::Buyer, $buyer->id, 'Changed my mind');

    expectReleased($buyer, $platform, $shop, $member);

    // The buyer can use both codes and the seat again at the group price.
    app(CartService::class)->addItem($buyer, $deal->variant->fresh(), 1);
    $again = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88, 'PLAT5', 'SHOP5');
    expect($again->subOrders->first()->items->first()->unit_price_sen)->toBe(6000)
        ->and($platform->fresh()->used_count)->toBe(1);
});

test('expiry of an unpaid order gives back the same three, once', function () {
    Notification::fake();
    Http::fake(['*' => Http::response('Record not found')]);
    [$order, $buyer, , $platform, $shop, $member] = entOrder();

    $order->update(['expires_at' => now()->subMinute()]);
    $this->artisan('orders:expire-unpaid')->assertSuccessful();
    $this->artisan('orders:expire-unpaid')->assertSuccessful(); // replay is a no-op

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Expired);
    expectReleased($buyer, $platform, $shop, $member);
});

test('a cancel after payment keeps the entitlements (refund path, not unpaid)', function () {
    [$order, $buyer, , $platform, $shop, $member] = entOrder();
    $order->update(['payment_status' => PaymentStatus::Paid]);
    $subOrder = $order->subOrders->first();
    app(SubOrderStatusService::class)->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);

    app(OrderService::class)->cancel($subOrder->fresh(), ActorType::Buyer, $buyer->id);

    expect($platform->fresh()->used_count)->toBe(1)
        ->and(VoucherUsage::where('user_id', $buyer->id)->count())->toBe(2)
        ->and($member->fresh()->status)->toBe(GroupBuyMemberStatus::Purchased);
});

test('cancel then a late expiry run gives back once and never touches another buyer\'s use', function () {
    Notification::fake();
    Http::fake(['*' => Http::response('Record not found')]);
    [$order, $buyer, , $platform] = entOrder();

    // A second buyer holds the same platform code on their own unpaid order.
    $platform->update(['quota' => 2]);
    $other = User::factory()->create();
    $other->assignRole('buyer');
    $otherAddress = Address::factory()->default()->create(['user_id' => $other->id, 'state' => 'Selangor']);
    app(CartService::class)->addItem($other, $order->subOrders->first()->items->first()->variant, 1);
    app(CheckoutService::class)->place($other, $otherAddress, PaymentMethod::Ipay88, 'PLAT5');
    expect($platform->fresh()->used_count)->toBe(2);

    app(OrderService::class)->cancelUnpaid($order, ActorType::Buyer, $buyer->id);
    $order->update(['expires_at' => now()->subMinute()]);
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($platform->fresh()->used_count)->toBe(1)
        ->and(VoucherUsage::where('voucher_id', $platform->id)->pluck('user_id')->all())->toBe([$other->id]);
});
