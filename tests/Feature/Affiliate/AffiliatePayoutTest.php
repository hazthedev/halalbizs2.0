<?php

use App\Enums\AffiliateReferralStatus;
use App\Exceptions\CheckoutException;
use App\Livewire\Storefront\Account\Affiliate as AffiliatePage;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AffiliateService;
use Livewire\Livewire;

beforeEach(fn () => config(['affiliate.enabled' => true, 'affiliate.min_payout_sen' => 5000]));

function affiliateWithEarnings(int $commissionSen)
{
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());

    AffiliateReferral::create([
        'affiliate_id' => $affiliate->id,
        'sub_order_id' => SubOrder::factory()->create()->id,
        'buyer_id' => User::factory()->create()->id,
        'items_subtotal_sen' => $commissionSen * 20,
        'commission_sen' => $commissionSen,
        'status' => AffiliateReferralStatus::Confirmed,
    ]);

    return $affiliate;
}

test('available equals confirmed earnings minus earmarked payouts', function () {
    $affiliate = affiliateWithEarnings(10_000);
    $svc = app(AffiliateService::class);

    expect($svc->availableForPayoutSen($affiliate))->toBe(10_000);

    $svc->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);

    expect($svc->availableForPayoutSen($affiliate))->toBe(4000);
});

test('a withdrawal below the minimum is rejected', function () {
    $affiliate = affiliateWithEarnings(10_000);
    app(AffiliateService::class)->requestPayout($affiliate, 1000, ['details' => 'Maybank 123']);
})->throws(CheckoutException::class);

test('a withdrawal above the available balance is rejected', function () {
    $affiliate = affiliateWithEarnings(5000);
    app(AffiliateService::class)->requestPayout($affiliate, 9000, ['details' => 'Maybank 123']);
})->throws(CheckoutException::class);

test('only one open withdrawal is allowed at a time', function () {
    $affiliate = affiliateWithEarnings(20_000);
    $svc = app(AffiliateService::class);
    $svc->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);
    $svc->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);
})->throws(CheckoutException::class);

test('rejecting a payout releases the earmark', function () {
    $affiliate = affiliateWithEarnings(10_000);
    $svc = app(AffiliateService::class);
    $payout = $svc->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);

    expect($svc->availableForPayoutSen($affiliate))->toBe(4000);

    $svc->rejectPayout($payout, 'incomplete bank details');

    expect($svc->availableForPayoutSen($affiliate))->toBe(10_000);
});

test('a paid payout stays earmarked', function () {
    $affiliate = affiliateWithEarnings(10_000);
    $svc = app(AffiliateService::class);
    $payout = $svc->requestPayout($affiliate, 6000, ['details' => 'Maybank 123']);

    $svc->markPayoutPaid($payout, 'TXN-1');

    expect($svc->availableForPayoutSen($affiliate))->toBe(4000)
        ->and($payout->fresh()->processed_at)->not->toBeNull();
});

test('the creator dashboard submits a withdrawal request', function () {
    $user = User::factory()->create();
    $affiliate = app(AffiliateService::class)->enroll($user);
    AffiliateReferral::create([
        'affiliate_id' => $affiliate->id,
        'sub_order_id' => SubOrder::factory()->create()->id,
        'buyer_id' => User::factory()->create()->id,
        'items_subtotal_sen' => 200_000,
        'commission_sen' => 10_000,
        'status' => AffiliateReferralStatus::Confirmed,
    ]);

    Livewire::actingAs($user)
        ->test(AffiliatePage::class)
        ->set('showWithdraw', true)
        ->set('withdrawAmount', '60.00')
        ->set('bankDetails', 'Maybank · Ali · 12345678')
        ->call('requestPayout')
        ->assertHasNoErrors();

    expect(AffiliatePayout::where('affiliate_id', $affiliate->id)->where('amount_sen', 6000)->count())->toBe(1);
});
