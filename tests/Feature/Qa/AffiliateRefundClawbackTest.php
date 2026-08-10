<?php

/**
 * Affiliate commission vs refunds.
 *
 * This file began as a characterisation test pinning a defect: commission was
 * booked payable the moment a referred sub-order completed, with no reversal
 * path, so a refund left withdrawable cash against an undone sale (RM5.00
 * before, RM5.00 after). Its own comment said "if a clawback is implemented,
 * THIS TEST SHOULD FAIL — update it then". It did, and this is that update.
 *
 * The shipped policy is a HOLD first: commission is `pending` until
 * delivered_at + return_window_days + lock_buffer_days, and a refund inside
 * that window reduces it pro-rata — quietly, because the creator was told the
 * money was not theirs yet. Slice 2 added the tail: a refund arriving AFTER the
 * hold reverses too, and if the money was already withdrawn the balance carries
 * a shortfall that later commissions absorb. Nobody is ever invoiced.
 */

use App\Enums\ActorType;
use App\Enums\AffiliateReferralStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\AffiliateReferral;
use App\Models\Product;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => test()->seed(RoleSeeder::class));

function arcBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function arcProduct(int $priceSen): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);

    return $product;
}

/** Drive a referred RM100 COD order all the way to completed. */
function arcCompletedReferredOrder(AffiliateService $service, $affiliate, int $priceSen = 10_000)
{
    [$buyer, $address] = arcBuyer();

    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);

    $product = arcProduct($priceSen);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $sub = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    return [$sub->fresh(), $buyer];
}

test('commission is held pending, not payable, the moment a referred order completes', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    // Booked at the full 5%, but NOT withdrawable — and the unlock date is a
    // real future instant the dashboard can show, not null.
    expect($referral->commission_sen)->toBe(500)
        ->and($referral->status)->toBe(AffiliateReferralStatus::Pending)
        ->and($referral->locks_at)->not->toBeNull()
        ->and($referral->locks_at->isFuture())->toBeTrue()
        ->and($service->confirmedEarningsSen($affiliate))->toBe(0)
        ->and($service->pendingEarningsSen($affiliate))->toBe(500);
});

test('a full refund inside the hold window voids the commission entirely', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    expect($service->pendingEarningsSen($affiliate))->toBe(500);

    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer);
    app(RefundService::class)->refund($sub->fresh(), (int) $sub->total_sen, ActorType::Seller, null);

    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    // The finding this file was opened for: RM5.00 survived a full refund.
    expect($sub->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($referral->reversed_sen)->toBe(500)
        ->and($referral->payableSen())->toBe(0)
        ->and($referral->status)->toBe(AffiliateReferralStatus::Reversed)
        ->and($service->pendingEarningsSen($affiliate))->toBe(0)
        ->and($service->confirmedEarningsSen($affiliate))->toBe(0);
});

test('a partial refund reduces the commission pro-rata rather than voiding it', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    // Refund 40% of the sub-order total.
    $total = (int) $sub->total_sen;
    app(RefundService::class)->refund($sub->fresh(), intdiv($total * 40, 100), ActorType::Seller, null);

    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    // 40% of RM5.00 goes, 60% survives — and it stays PENDING, because a
    // partial refund is not a reversal of the sale.
    expect($referral->reversed_sen)->toBe(200)
        ->and($referral->payableSen())->toBe(300)
        ->and($referral->status)->toBe(AffiliateReferralStatus::Pending)
        ->and($service->pendingEarningsSen($affiliate))->toBe(300);
});

test('two partial refunds sum to the whole and never over-reverse', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    $total = (int) $sub->total_sen;
    $refunds = app(RefundService::class);

    // Half, then the rest, then a third call with nothing left to give.
    $refunds->refund($sub->fresh(), intdiv($total, 2), ActorType::Seller, null);
    $refunds->refund($sub->fresh(), $total - intdiv($total, 2), ActorType::Seller, null);
    $refunds->refund($sub->fresh(), $total, ActorType::Seller, null);

    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    // Rounding must not let the parts exceed the whole.
    expect($referral->reversed_sen)->toBe(500)
        ->and($referral->payableSen())->toBe(0);
});

test('the hold expires on schedule and the commission becomes payable', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    // Nothing is due yet.
    expect($service->lockDueCommissions())->toBe(0)
        ->and($service->confirmedEarningsSen($affiliate))->toBe(0);

    $locksAt = AffiliateReferral::where('sub_order_id', $sub->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locksAt)->addMinute());

    expect($service->lockDueCommissions())->toBe(1)
        ->and($service->confirmedEarningsSen($affiliate))->toBe(500)
        ->and($service->pendingEarningsSen($affiliate))->toBe(0);

    // Idempotent: a second run moves nothing and changes no balance.
    expect($service->lockDueCommissions())->toBe(0)
        ->and($service->confirmedEarningsSen($affiliate))->toBe(500);
});

// (The slice-1 marker test that pinned "a refund after the hold is left alone"
//  lived here. It said it SHOULD change when slice 2 shipped, and it did — the
//  post-lock behaviour is now covered by the slice 2 block at the end of this
//  file. Removed rather than left contradicting its successor.)

/**
 * SLICE 2 — the tail the hold cannot catch: a refund arriving AFTER the
 * commission unlocked, possibly after it was withdrawn.
 *
 * Policy: net against future earnings, never invoice. The creator is never
 * asked for money; their balance simply carries a shortfall that later
 * commissions absorb.
 */
test('a refund after the hold expires now reverses the commission', function () {
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate);

    $locksAt = AffiliateReferral::where('sub_order_id', $sub->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locksAt)->addMinute());
    $service->lockDueCommissions();

    expect($service->confirmedEarningsSen($affiliate))->toBe(500);

    app(RefundService::class)->refund($sub->fresh(), (int) $sub->total_sen, ActorType::Seller, null);

    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    expect($referral->status)->toBe(AffiliateReferralStatus::Reversed)
        ->and($referral->reversed_sen)->toBe(500)
        ->and($service->confirmedEarningsSen($affiliate))->toBe(0);
});

test('a shortfall on already-withdrawn commission carries forward as a negative balance', function () {
    // COD is capped at RM500, so a single referred order tops out at RM25
    // commission — below the RM50 default payout floor. Lower the floor: what is
    // under test here is the netting, not the minimum.
    config(['affiliate.min_payout_sen' => 100]);
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate, 40_000); // RM400 -> RM20 commission

    $locksAt = AffiliateReferral::where('sub_order_id', $sub->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locksAt)->addMinute());
    $service->lockDueCommissions();

    // They withdraw the lot.
    $service->requestPayout($affiliate, 2_000);
    expect($service->availableForPayoutSen($affiliate))->toBe(0);

    // Then the sale comes back.
    app(RefundService::class)->refund($sub->fresh(), (int) $sub->total_sen, ActorType::Seller, null);

    // THE POINT: the debt persists instead of being forgiven. availableForPayout
    // used to be clamped with max(0, …), which silently wrote this off.
    expect($service->availableForPayoutSen($affiliate))->toBe(-2_000);
});

test('future commissions absorb the shortfall, and nobody is ever billed', function () {
    // COD is capped at RM500, so a single referred order tops out at RM25
    // commission — below the RM50 default payout floor. Lower the floor: what is
    // under test here is the netting, not the minimum.
    config(['affiliate.min_payout_sen' => 100]);
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate, 40_000); // RM400 -> RM20 commission
    $locksAt = AffiliateReferral::where('sub_order_id', $sub->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locksAt)->addMinute());
    $service->lockDueCommissions();
    $service->requestPayout($affiliate, 2_000);
    app(RefundService::class)->refund($sub->fresh(), (int) $sub->total_sen, ActorType::Seller, null);

    expect($service->availableForPayoutSen($affiliate))->toBe(-2_000);

    // A later, larger sale.
    [$sub2] = arcCompletedReferredOrder($service, $affiliate, 45_000); // RM450 -> RM22.50 commission
    $locks2 = AffiliateReferral::where('sub_order_id', $sub2->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locks2)->addMinute());
    $service->lockDueCommissions();

    // RM150 earned less the RM100 shortfall = RM50 withdrawable. Netted, not billed.
    expect($service->availableForPayoutSen($affiliate))->toBe(250); // RM22.50 earned - RM20 shortfall
});

test('a withdrawal is refused while the balance is still negative', function () {
    // COD is capped at RM500, so a single referred order tops out at RM25
    // commission — below the RM50 default payout floor. Lower the floor: what is
    // under test here is the netting, not the minimum.
    config(['affiliate.min_payout_sen' => 100]);
    $service = app(AffiliateService::class);
    $affiliate = $service->enroll(User::factory()->create());

    [$sub] = arcCompletedReferredOrder($service, $affiliate, 40_000);
    $locksAt = AffiliateReferral::where('sub_order_id', $sub->id)->value('locks_at');
    $this->travelTo(\Illuminate\Support\Carbon::parse($locksAt)->addMinute());
    $service->lockDueCommissions();
    $service->requestPayout($affiliate, 2_000);
    app(AffiliateService::class)->markPayoutPaid($affiliate->payouts()->first());
    app(RefundService::class)->refund($sub->fresh(), (int) $sub->total_sen, ActorType::Seller, null);

    expect(fn () => $service->requestPayout($affiliate, 1_000))
        ->toThrow(App\Exceptions\CheckoutException::class);
});
