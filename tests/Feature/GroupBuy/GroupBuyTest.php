<?php

use App\Enums\GroupBuyMemberStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\GroupBuyTeamStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GroupBuyService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['groupbuy.enabled' => true]);
});

function gbDeal(int $groupPriceSen = 6000, int $target = 2, int $normalSen = 10_000): GroupBuy
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $normalSen, 'sale_price_sen' => null, 'stock' => 20]);

    return GroupBuy::create([
        'store_id' => $product->store_id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => $groupPriceSen,
        'target_size' => $target,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addWeek(),
    ]);
}

function gbBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

test('starting a team adds the initiator and stays forming below target', function () {
    $team = app(GroupBuyService::class)->startTeam(User::factory()->create(), gbDeal(target: 3));

    expect($team->status)->toBe(GroupBuyTeamStatus::Forming)
        ->and($team->members()->count())->toBe(1);
});

test('the target-th join unlocks the team', function () {
    $deal = gbDeal(target: 2);
    $svc = app(GroupBuyService::class);

    $team = $svc->startTeam(User::factory()->create(), $deal);
    $svc->joinTeam(User::factory()->create(), $team);

    expect($team->fresh()->status)->toBe(GroupBuyTeamStatus::Unlocked)
        ->and($team->fresh()->completed_at)->not->toBeNull();
});

test('joining is idempotent for an existing member', function () {
    $deal = gbDeal(target: 3);
    $svc = app(GroupBuyService::class);
    $user = User::factory()->create();

    $team = $svc->startTeam($user, $deal);
    $svc->joinTeam($user, $team);

    expect($team->fresh()->members()->count())->toBe(1);
});

test('an expired team can no longer be joined', function () {
    $svc = app(GroupBuyService::class);
    $team = $svc->startTeam(User::factory()->create(), gbDeal(target: 3));
    $team->update(['expires_at' => now()->subHour()]);

    $svc->joinTeam(User::factory()->create(), $team);
})->throws(CheckoutException::class);

test('expiring closes lapsed forming teams but leaves unlocked teams alone', function () {
    $svc = app(GroupBuyService::class);

    $forming = $svc->startTeam(User::factory()->create(), gbDeal(target: 3));
    $forming->update(['expires_at' => now()->subHour()]);

    $unlocked = $svc->startTeam(User::factory()->create(), gbDeal(target: 1)); // unlocks immediately
    expect($unlocked->fresh()->status)->toBe(GroupBuyTeamStatus::Unlocked);

    expect($svc->expireDueTeams())->toBe(1)
        ->and($forming->fresh()->status)->toBe(GroupBuyTeamStatus::Expired)
        ->and($unlocked->fresh()->status)->toBe(GroupBuyTeamStatus::Unlocked);
});

test('an unlocked membership prices the line at the group price and burns the membership', function () {
    [$buyer, $address] = gbBuyer();
    $deal = gbDeal(6000, 2);

    $svc = app(GroupBuyService::class);
    $team = $svc->startTeam($buyer, $deal);
    $svc->joinTeam(User::factory()->create(), $team);
    expect($team->fresh()->status)->toBe(GroupBuyTeamStatus::Unlocked);

    app(CartService::class)->addItem($buyer, $deal->variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $item = $order->subOrders->first()->items->first();
    expect($item->unit_price_sen)->toBe(6000)
        ->and($item->group_buy_id)->toBe($deal->id)
        ->and($order->grand_total_sen)->toBe(6000); // group price, no shipping/tax

    $member = GroupBuyMember::where('group_buy_team_id', $team->id)->where('user_id', $buyer->id)->first();
    expect($member->status)->toBe(GroupBuyMemberStatus::Purchased)
        ->and($member->sub_order_id)->toBe($order->subOrders->first()->id)
        ->and($deal->variant->fresh()->stock)->toBe(19);
});

test('a forming (not-yet-unlocked) team does not change the checkout price', function () {
    [$buyer, $address] = gbBuyer();
    $deal = gbDeal(6000, 3); // needs 3, only the buyer joins → forming

    app(GroupBuyService::class)->startTeam($buyer, $deal);
    app(CartService::class)->addItem($buyer, $deal->variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $item = $order->subOrders->first()->items->first();
    expect($item->unit_price_sen)->toBe(10_000)
        ->and($item->group_buy_id)->toBeNull();
});

test('a redeemed membership is not applied twice', function () {
    [$buyer, $address] = gbBuyer();
    $deal = gbDeal(6000, 2);

    $svc = app(GroupBuyService::class);
    $team = $svc->startTeam($buyer, $deal);
    $svc->joinTeam(User::factory()->create(), $team);

    app(CartService::class)->addItem($buyer, $deal->variant, 1);
    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    // Buy the same variant again — group price is spent, normal price applies.
    app(CartService::class)->addItem($buyer, $deal->variant, 1);
    $second = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($second->subOrders->first()->items->first()->unit_price_sen)->toBe(10_000);
});
