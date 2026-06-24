<?php

use App\Enums\AffiliatePayoutStatus;
use App\Enums\AffiliateReferralStatus;
use App\Enums\CoinTransactionType;
use App\Enums\LiveSessionStatus;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionInterval;
use App\Livewire\Admin\Affiliates\Index as AdminAffiliates;
use App\Livewire\Admin\Coins\Index as AdminCoins;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Live\Index as AdminLive;
use App\Livewire\Admin\Subscriptions\Index as AdminSubscriptions;
use App\Models\Address;
use App\Models\AffiliateReferral;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CoinService;
use App\Services\LiveSessionService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'affiliate.enabled' => true, 'subscriptions.enabled' => true, 'live.enabled' => true]);
});

function m2Admin(): User
{
    $admin = User::factory()->create(['two_factor_method' => 'email']); // EnsureAdmin requires 2FA
    $admin->assignRole('admin');

    return $admin;
}

test('an admin can grant and claw back coins', function () {
    $admin = m2Admin();
    $buyer = User::factory()->create();
    app(CoinService::class)->credit($buyer, 100, CoinTransactionType::Earn);

    Livewire::actingAs($admin)->test(AdminCoins::class)
        ->call('openAdjust', $buyer->id)
        ->set('adjustCoins', '50')
        ->set('adjustReason', 'Goodwill credit')
        ->call('adjust')
        ->assertHasNoErrors();
    expect(app(CoinService::class)->balance($buyer))->toBe(150);

    Livewire::actingAs($admin)->test(AdminCoins::class)
        ->call('openAdjust', $buyer->id)
        ->set('adjustCoins', '-30')
        ->set('adjustReason', 'Reversal')
        ->call('adjust');
    expect(app(CoinService::class)->balance($buyer))->toBe(120);
});

test('an admin can mark an affiliate withdrawal paid', function () {
    config(['affiliate.min_payout_sen' => 5000]);
    $admin = m2Admin();
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    AffiliateReferral::create([
        'affiliate_id' => $affiliate->id,
        'sub_order_id' => SubOrder::factory()->create()->id,
        'buyer_id' => User::factory()->create()->id,
        'items_subtotal_sen' => 200_000,
        'commission_sen' => 10_000,
        'status' => AffiliateReferralStatus::Confirmed,
    ]);
    $payout = app(AffiliateService::class)->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);

    Livewire::actingAs($admin)->test(AdminAffiliates::class)
        ->call('openPay', $payout->id)
        ->set('paidReference', 'TXN-99')
        ->call('markPaid')
        ->assertHasNoErrors();

    expect($payout->fresh()->status)->toBe(AffiliatePayoutStatus::Paid);
});

test('an admin can force-end a live session', function () {
    $admin = m2Admin();
    $seller = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);
    $session = app(LiveSessionService::class)->create($store, ['title' => 'Bad Stream']);
    app(LiveSessionService::class)->goLive($session);

    Livewire::actingAs($admin)->test(AdminLive::class)
        ->call('forceEnd', $session->id);

    expect($session->fresh()->status)->toBe(LiveSessionStatus::Ended);
});

test('the admin subscriptions page lists an active subscription', function () {
    $admin = m2Admin();
    $buyer = User::factory()->create(['name' => 'Aisyah Buyer']);
    $address = Address::factory()->default()->create(['user_id' => $buyer->id]);
    $product = Product::factory()->create(['cod_enabled' => true]);
    app(SubscriptionService::class)->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Monthly);

    Livewire::actingAs($admin)->test(AdminSubscriptions::class)
        ->assertOk()
        ->assertSee('Aisyah Buyer');
});

test('the admin dashboard renders the M2 KPI row', function () {
    Livewire::actingAs(m2Admin())->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('Coins in circulation'))
        ->assertSee(__('Group-buy unlock rate'));
});

test('the new admin routes are reachable by an admin', function () {
    $admin = m2Admin();

    $this->actingAs($admin)->get(route('admin.coins.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.affiliates.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.live.index'))->assertOk();
});
