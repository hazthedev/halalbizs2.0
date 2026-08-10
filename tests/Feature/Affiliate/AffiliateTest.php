<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Affiliate as AffiliatePage;
use App\Models\Address;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['affiliate.enabled' => true, 'affiliate.commission_rate_bp' => 500]); // 5%
});

function affiliateBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function affiliateProduct(int $priceSen): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);

    return $product;
}

test('enrolment mints a unique code and is idempotent', function () {
    $user = User::factory()->create();
    $svc = app(AffiliateService::class);

    $first = $svc->enroll($user);
    $second = $svc->enroll($user);

    expect($first->code)->not->toBe('')
        ->and($first->id)->toBe($second->id)
        ->and($first->commission_rate_bp)->toBe(500);
});

test('the share link records a click, drops the cookie and redirects safely', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());

    $response = $this->get('/r/'.$affiliate->code.'?to=/cart');

    $response->assertRedirect('/cart');
    expect($affiliate->fresh()->clicks)->toBe(1)
        ->and(collect($response->headers->getCookies())->contains(fn ($c) => $c->getName() === config('affiliate.cookie')))->toBeTrue();
});

test('the share link refuses external redirect targets', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());

    $this->get('/r/'.$affiliate->code.'?to=https://evil.test')->assertRedirect(route('home'));
});

test('an order is attributed to the affiliate named in the request cookie', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    $buyer = User::factory()->create();

    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    expect($order->fresh()->affiliate_id)->toBe($affiliate->id);
});

test('a creator cannot self-refer', function () {
    $creator = User::factory()->create();
    $affiliate = app(AffiliateService::class)->enroll($creator);

    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);
    $order = Order::factory()->create(['user_id' => $creator->id]);

    expect($order->fresh()->affiliate_id)->toBeNull();
});

test('completing a referred sub-order books commission exactly once', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    [$buyer, $address] = affiliateBuyer();

    request()->cookies->set((string) config('affiliate.cookie'), $affiliate->code);

    $product = affiliateProduct(10_000); // RM100
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    expect($order->affiliate_id)->toBe($affiliate->id);

    $sub = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($sub->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($sub->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($sub->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($sub->fresh(), $buyer->id);

    // Booked at the full 5%, but HELD: commission is pending until the return
    // window plus buffer has passed, so confirmed (payable) earnings are still
    // zero and the amount sits in pending. See AffiliateRefundClawbackTest for
    // the hold expiring and for refunds landing inside it.
    $referral = AffiliateReferral::where('sub_order_id', $sub->id)->first();
    expect($referral)->not->toBeNull()
        ->and($referral->commission_sen)->toBe(500) // 5% of RM100
        ->and(app(AffiliateService::class)->confirmedEarningsSen($affiliate))->toBe(0)
        ->and(app(AffiliateService::class)->pendingEarningsSen($affiliate))->toBe(500);

    // Re-running the booking is a no-op.
    app(AffiliateService::class)->recordCommission($sub->fresh());
    expect(AffiliateReferral::where('sub_order_id', $sub->id)->count())->toBe(1);
});

test('a buyer can enrol from the dashboard and see their share link', function () {
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(AffiliatePage::class)
        ->assertSee(__('Join the creator program'))
        ->call('enroll')
        ->assertSee('/r/');

    expect($buyer->fresh()->affiliate)->not->toBeNull();
});

test('the creator dashboard 404s when the program is disabled', function () {
    config(['affiliate.enabled' => false]);

    Livewire::actingAs(User::factory()->create())
        ->test(AffiliatePage::class)
        ->assertStatus(404);
});

/**
 * The programme terms. Slice 4's finding was that nothing in the affiliate
 * programme was binding — no page, no document — while the code had opinions
 * about holds, pro-rata reductions and carry-forward balances. This page is the
 * record of shipped behaviour, so it must exist and be reachable.
 */
it('publishes the creator programme terms and links them before anyone joins', function () {
    $this->seed(Database\Seeders\PageSeeder::class);

    $this->get(route('page.show', 'affiliate-terms'))
        ->assertOk()
        ->assertSee('Creator Programme Terms')
        // The two clauses that exist because of what shipped today. If either
        // stops being true in code, this page is lying and must change with it.
        ->assertSee('never invoice you', false)
        ->assertSee('reduced in proportion', false);

    // Reachable from the enrol screen, not just by URL — the hold only reads as
    // fair if it was visible before signing up.
    $buyer = App\Models\User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(App\Livewire\Storefront\Account\Affiliate::class)
        ->assertSee('Read the programme terms');
});
