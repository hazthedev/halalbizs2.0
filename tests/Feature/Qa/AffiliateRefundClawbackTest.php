<?php

/**
 * QA probe — does an affiliate keep commission on money that was refunded?
 *
 * AffiliateService books commission when a referred sub-order COMPLETES, and
 * the transition map allows completed -> return_requested -> refunded
 * (SubOrderStatusService:27-29). A grep of AffiliateService finds no refund,
 * cancel or reverse path. This asserts what actually happens rather than
 * concluding from the grep.
 */

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use App\Models\Address;
use App\Models\Product;
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

test('affiliate commission is NOT reversed when the referred order is refunded', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    [$buyer, $address] = arcBuyer();

    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);

    $product = arcProduct(10_000); // RM100
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $sub = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    $bookedSen = AffiliateReferral::where('sub_order_id', $sub->id)->value('commission_sen');
    $earnedBefore = app(AffiliateService::class)->confirmedEarningsSen($affiliate);

    // The buyer returns it and the seller refunds — a reachable path.
    $status->transition($sub->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer);
    $status->transition($sub->fresh(), SubOrderStatus::Refunded, ActorType::Seller);

    $earnedAfter = app(AffiliateService::class)->confirmedEarningsSen($affiliate);
    $referralAfter = AffiliateReferral::where('sub_order_id', $sub->id)->first();

    // CURRENT BEHAVIOUR, pinned deliberately — not an endorsement.
    //
    // The buyer's money went back; the affiliate's RM5.00 did not. Confirmed
    // earnings are what `available` withdrawals are computed from
    // (AffiliateService::availableSen), so this is withdrawable cash booked
    // against a sale that was reversed.
    //
    // Whether to claw back is a PROGRAM POLICY call, not something to pick in a
    // test: some programs reverse on refund, some hold commission until the
    // return window closes, some keep it deliberately as the cost of acquisition.
    // Nothing in the code or docs states an intent either way, so this test
    // records what the system does today. If a clawback is implemented, THIS
    // TEST SHOULD FAIL — update it then, and delete this comment.
    expect($sub->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($bookedSen)->toBe(500)
        ->and($earnedBefore)->toBe(500)
        ->and($earnedAfter)->toBe(500)          // <- the finding
        ->and($referralAfter)->not->toBeNull()
        ->and($referralAfter->commission_sen)->toBe(500);
});
