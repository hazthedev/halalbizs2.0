<?php

/**
 * QA pass — checkout money math + vouchers (real money).
 *
 * Every test here is written so that it FAILS if the defect it names is real.
 * Tests that pass are recorded as "verified correct", not as findings.
 */

use App\Enums\ActorType;
use App\Enums\CommissionBasis;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Storefront\Checkout;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\OrderService;
use App\Services\RefundService;
use App\Services\SpinService;
use App\Services\SubOrderStatusService;
use App\Services\TaxService;
use App\Services\VoucherService;
use App\Settings\CommissionSettings;
use App\Support\Money;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// ── fixtures ───────────────────────────────────────────────────────────────

function qaCvBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create([
        'user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY',
    ]);

    return [$buyer, $address];
}

function qaCvStore(int $flatFeeSen = 500, bool $sstRegistered = false, ?float $commissionRate = 5.0): Store
{
    return Store::factory()->approved()->create([
        'holiday_mode' => false,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => $flatFeeSen,
        'shipping_matrix' => null,
        'free_shipping_over_sen' => null,
        'sst_registered' => $sstRegistered,
        'commission_rate' => $commissionRate,
    ]);
}

function qaCvVariant(Store $store, int $priceSen, TaxClass $taxClass = TaxClass::Exempt): ProductVariant
{
    $product = Product::factory()->create([
        'store_id' => $store->id,
        'cod_enabled' => true,
        'tax_class' => $taxClass,
        'weight_grams' => 100,
    ]);

    $product->variants()->first()->update([
        'price_sen' => $priceSen, 'sale_price_sen' => null,
        'sale_starts_at' => null, 'sale_ends_at' => null, 'stock' => 500,
    ]);

    return $product->variants()->first()->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function qaCvVoucher(array $overrides): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Platform,
        'store_id' => null,
        'type' => VoucherType::Fixed,
        'value_sen' => 0,
        'min_spend_sen' => 0,
        'quota' => null,
        'per_user_limit' => 1,
        'used_count' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ], $overrides));
}

/** Walk a sub-order all the way to Completed — the moment the ledger books. */
function qaCvComplete(SubOrder $subOrder, User $buyer): SubOrder
{
    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);

    return $subOrder->fresh();
}

/** The grand total as actually PAINTED in the checkout summary. */
function qaCvRenderedGrandTotal(string $html): string
{
    expect($html)->toMatch('/Grand total/');

    preg_match('/Grand total.*?text-ink-head tnum">\s*([^<]+?)\s*</s', $html, $m);

    return trim($m[1] ?? 'NOT-FOUND');
}

// ═══════════════════════════════════════════════════════════════════════════
// PART A — selectVoucher(int $voucherId): can the picker be bypassed?
// ═══════════════════════════════════════════════════════════════════════════

test('A1 a platform-scope voucher carrying a store_id is neither offered by the picker nor appliable via selectVoucher', function () {
    [$buyer] = qaCvBuyer();
    $storeA = qaCvStore();
    $storeB = qaCvStore();
    $variantA = qaCvVariant($storeA, 10000);

    // The exact divergence case from the brief: scope=Platform BUT store_id set.
    // The picker's whereNull('store_id') hides it. Does selectVoucher take it?
    $rogue = qaCvVoucher([
        'scope' => VoucherScope::Platform, 'store_id' => $storeB->id,
        'code' => 'ROGUEPLAT', 'type' => VoucherType::Fixed, 'value_sen' => 5000,
    ]);

    app(CartService::class)->addItem($buyer, $variantA, 1);

    // Open the picker drawer the way a buyer does — the options only paint then.
    $component = Livewire::actingAs($buyer)->test(Checkout::class)->set('voucherPanelOpen', true);

    expect($component->html())->toContain('Platform vouchers');  // the drawer really is open
    expect($component->html())->not->toContain('ROGUEPLAT');

    // And not appliable by id either.
    $component->call('selectVoucher', $rogue->id);

    expect($component->get('appliedPlatformCode'))->toBeNull()
        ->and($component->get('appliedShopCode'))->toBeNull()
        ->and($component->get('appliedShippingCode'))->toBeNull()
        ->and($component->get('voucherError'))->not->toBeNull();

    // Rendered proof: no discount line, grand total is untouched.
    expect(qaCvRenderedGrandTotal($component->html()))->toBe(Money::format(10000 + 500));
});

test('A2 selectVoucher rejects a shop voucher whose store is not in the cart', function () {
    [$buyer] = qaCvBuyer();
    $storeA = qaCvStore();
    $storeB = qaCvStore();
    $variantA = qaCvVariant($storeA, 10000);
    qaCvVariant($storeB, 10000);

    $foreign = qaCvVoucher([
        'scope' => VoucherScope::Shop, 'store_id' => $storeB->id,
        'code' => 'OTHERSHOP', 'type' => VoucherType::Fixed, 'value_sen' => 5000,
    ]);

    app(CartService::class)->addItem($buyer, $variantA, 1);

    $component = Livewire::actingAs($buyer)->test(Checkout::class)->set('voucherPanelOpen', true);

    expect($component->html())->toContain('Shop vouchers');
    expect($component->html())->not->toContain('OTHERSHOP');

    $component->call('selectVoucher', $foreign->id);

    expect($component->get('appliedShopCode'))->toBeNull()
        ->and($component->get('voucherError'))->toContain('different shop');
});

/**
 * SpinService mints "a single-use personal platform voucher (quota 1) whose
 * code is only shown to the winner" (its own docblock). The row carries no
 * owner, so voucherOptions() lists every active platform voucher to everyone
 * — and selectVoucher(id) redeems it without the secret code ever being typed.
 *
 * @return array{0: User, 1: Voucher, 2: ProductVariant}
 */
function qaCvSpinPrizeAndStranger(): array
{
    [$winner] = qaCvBuyer();
    [$stranger] = qaCvBuyer();

    $prize = app(SpinService::class)->grant($winner, [
        'type' => 'voucher', 'voucher' => 'fixed', 'value_sen' => 2000, 'min_spend_sen' => 0,
    ])['voucher'];

    expect($prize->quota)->toBe(1)
        ->and((int) $prize->per_user_limit)->toBe(1)
        ->and($prize->scope)->toBe(VoucherScope::Platform);

    $variant = qaCvVariant(qaCvStore(flatFeeSen: 0), 10000);
    app(CartService::class)->addItem($stranger, $variant, 1);

    return [$stranger, $prize, $variant];
}

test('A3a a spin-won personal voucher code is not painted into a stranger checkout picker', function () {
    [$stranger, $prize] = qaCvSpinPrizeAndStranger();

    $html = Livewire::actingAs($stranger)->test(Checkout::class)
        ->set('voucherPanelOpen', true)
        ->html();

    expect($html)->toContain('Platform vouchers');   // the drawer really is open
    expect($html)->not->toContain($prize->code);
});

test('A3b a stranger cannot redeem a spin-won personal voucher by id', function () {
    [$stranger, $prize] = qaCvSpinPrizeAndStranger();

    Livewire::actingAs($stranger)->test(Checkout::class)
        ->call('selectVoucher', $prize->id)
        ->call('placeOrder');

    $order = Order::where('user_id', $stranger->id)->first();

    expect((int) ($order?->discount_total_sen ?? 0))->toBe(0)
        ->and((int) $prize->fresh()->used_count)->toBe(0);
});

test('A4 a platform-scope free-shipping voucher bound to one store waives shipping for EVERY store in the cart', function () {
    // Companion to A1: the picker hides scope=Platform + store_id rows, and
    // selectVoucher can't reach them — but the manual code box validates with
    // scope=null, where lookup() prefers ANY platform row, store_id or not.
    [$buyer, $address] = qaCvBuyer();
    $storeA = qaCvStore(flatFeeSen: 700);
    $storeB = qaCvStore(flatFeeSen: 900);
    $variantA = qaCvVariant($storeA, 10000);
    $variantB = qaCvVariant($storeB, 5000);

    qaCvVoucher([
        'scope' => VoucherScope::Platform, 'store_id' => $storeA->id,
        'code' => 'SHIPA-ONLY', 'type' => VoucherType::FreeShipping,
    ]);

    app(CartService::class)->addItem($buyer, $variantA, 1);
    app(CartService::class)->addItem($buyer, $variantB, 1);

    $component = Livewire::actingAs($buyer)->test(Checkout::class)->set('voucherPanelOpen', true);

    // Invisible in the picker...
    expect($component->html())->toContain('Platform vouchers');
    expect($component->html())->not->toContain('SHIPA-ONLY');

    // ...but the manual box takes it, and CheckoutService places the order.
    $component->set('voucherCode', 'SHIPA-ONLY')->call('applyVoucher');

    expect($component->get('appliedShippingCode'))->toBe('SHIPA-ONLY');

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, null, [], 'SHIPA-ONLY');

    // Store B's shipping was waived too, on a voucher bound to store A.
    $subB = $order->subOrders->firstWhere('store_id', $storeB->id);

    expect((int) $order->shipping_total_sen)->toBe(0)
        ->and((int) $subB->shipping_subsidy_sen)->toBe(900);
});

// ═══════════════════════════════════════════════════════════════════════════
// PART B — money math
// ═══════════════════════════════════════════════════════════════════════════

test('B1 a multi-store cart reconciles against an independently recomputed bill', function () {
    [$buyer, $address] = qaCvBuyer();

    // Store A: SST-registered, standard-rated goods (10%), flat RM7 shipping.
    $storeA = qaCvStore(flatFeeSen: 700, sstRegistered: true);
    $variantA = qaCvVariant($storeA, 3333, TaxClass::Standard);

    // Store B: not registered (no tax), flat RM5 shipping.
    $storeB = qaCvStore(flatFeeSen: 500);
    $variantB = qaCvVariant($storeB, 5000);

    qaCvVoucher(['code' => 'PLAT15', 'type' => VoucherType::Percent, 'percent' => 15, 'per_user_limit' => 5]);
    qaCvVoucher([
        'scope' => VoucherScope::Shop, 'store_id' => $storeB->id,
        'code' => 'SHOPB10', 'type' => VoucherType::Percent, 'percent' => 10, 'per_user_limit' => 5,
    ]);

    app(CartService::class)->addItem($buyer, $variantA, 3); // 9,999
    app(CartService::class)->addItem($buyer, $variantB, 2); // 10,000

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'PLAT15', 'SHOPB10');

    // Independent recomputation, from the DB prices only.
    $subA = 3333 * 3;                                   // 9,999
    $subB = 5000 * 2;                                   // 10,000
    $subtotal = $subA + $subB;                          // 19,999
    $taxA = intdiv($subA * 1000 + 5000, 10000);         // 10% half-up = 1,000
    $taxB = 0;
    $shipping = 700 + 500;                              // 1,200
    $shopDiscount = intdiv($subB * 1000, 10000);        // 10% of store B = 1,000
    $platformDiscount = intdiv($subtotal * 1500, 10000); // 15% of 19,999 = 2,999
    $grand = $subtotal + $shipping + $taxA + $taxB - $platformDiscount - $shopDiscount;

    expect((int) $order->subtotal_sen)->toBe($subtotal)
        ->and((int) $order->shipping_total_sen)->toBe($shipping)
        ->and((int) $order->tax_total_sen)->toBe($taxA + $taxB)
        ->and((int) $order->discount_total_sen)->toBe($platformDiscount)
        ->and((int) $order->subOrders->sum('shop_discount_sen'))->toBe($shopDiscount)
        ->and((int) $order->grand_total_sen)->toBe($grand);

    // Sub-orders must add back up to the order (platform discount lives at order level).
    expect((int) $order->subOrders->sum('total_sen') - (int) $order->discount_total_sen)
        ->toBe((int) $order->grand_total_sen);

    // The payment row must ask for exactly the grand total.
    expect((int) $order->payment->amount_sen)->toBe($grand);
});

test('B2 the grand total the buyer is SHOWN is the grand total they are CHARGED', function () {
    // Preview:  min(platform, subtotal)                        (Checkout.php:264)
    // Charged:  min(platform, subtotal − shopDiscount)         (CheckoutService.php:341)
    [$buyer] = qaCvBuyer();
    $store = qaCvStore(flatFeeSen: 3000);
    $variant = qaCvVariant($store, 10000);

    qaCvVoucher(['code' => 'PLAT60', 'type' => VoucherType::Percent, 'percent' => 60]);
    qaCvVoucher([
        'scope' => VoucherScope::Shop, 'store_id' => $store->id,
        'code' => 'SHOP60', 'type' => VoucherType::Percent, 'percent' => 60,
    ]);

    app(CartService::class)->addItem($buyer, $variant, 1);

    $component = Livewire::actingAs($buyer)->test(Checkout::class)
        ->set('voucherCode', 'PLAT60')->call('applyVoucher')
        ->set('voucherCode', 'SHOP60')->call('applyVoucher');

    $shown = qaCvRenderedGrandTotal($component->html());

    $component->call('placeOrder');

    $order = Order::where('user_id', $buyer->id)->first();

    expect($order)->not->toBeNull();
    expect($shown)->toBe(Money::format((int) $order->grand_total_sen));
});

test('B3 the commission booked to the seller ledger matches the configured basis', function () {
    foreach ([[CommissionBasis::Net, 400], [CommissionBasis::Gross, 500]] as [$basis, $expected]) {
        $settings = app(CommissionSettings::class);
        $settings->discount_basis = $basis->value;
        $settings->save();

        [$buyer, $address] = qaCvBuyer();
        $store = qaCvStore(flatFeeSen: 0, commissionRate: 5.0);
        $variant = qaCvVariant($store, 10000);

        $code = 'SHOP20'.strtoupper($basis->value);
        qaCvVoucher([
            'scope' => VoucherScope::Shop, 'store_id' => $store->id,
            'code' => $code, 'type' => VoucherType::Percent, 'percent' => 20,
        ]);

        app(CartService::class)->addItem($buyer, $variant, 1);
        $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, $code);
        $sub = qaCvComplete($order->subOrders->first(), $buyer);

        $saleSen = (int) $sub->ledgerEntries()->where('type', LedgerEntryType::Sale)->value('amount_sen');
        $commissionSen = (int) $sub->ledgerEntries()->where('type', LedgerEntryType::Commission)->value('amount_sen');

        expect((int) $sub->commission_sen)->toBe($expected)
            ->and($commissionSen)->toBe(-$expected)
            ->and($saleSen)->toBe(8000)                 // items 10,000 − shop voucher 2,000
            ->and((int) $sub->total_sen)->toBe(8000);
    }
});

test('B4 a platform-funded free-shipping voucher must not be paid for by the seller', function () {
    // docs/ROADMAP.md M1.1: "CheckoutService writes a shipping_subsidy_sen line;
    // LedgerService attributes subsidy cost to the funder."
    $build = function (?string $shippingCode): array {
        [$buyer, $address] = qaCvBuyer();
        $store = qaCvStore(flatFeeSen: 900, commissionRate: 5.0);
        $variant = qaCvVariant($store, 10000);

        if ($shippingCode !== null) {
            qaCvVoucher(['code' => $shippingCode, 'type' => VoucherType::FreeShipping]);
        }

        app(CartService::class)->addItem($buyer, $variant, 1);
        $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, null, [], $shippingCode);
        $sub = qaCvComplete($order->subOrders->first(), $buyer);

        return [
            'sale' => (int) $sub->ledgerEntries()->where('type', LedgerEntryType::Sale)->value('amount_sen'),
            'subsidy' => (int) $sub->shipping_subsidy_sen,
            'sub' => $sub,
        ];
    };

    $without = $build(null);
    $with = $build('QAFREESHIP');

    expect($without['sale'])->toBe(10900)          // items 10,000 + shipping 900
        ->and($with['subsidy'])->toBe(900);        // the waiver IS recorded

    // ...but nothing credits it back, so the seller earns 900 sen less on a
    // promo the PLATFORM ran. Under the codebase's own stated rule (platform
    // vouchers never reduce what the seller is paid) these must be equal.
    $creditedBack = (int) $with['sub']->ledgerEntries()
        ->whereIn('type', [LedgerEntryType::Sale, LedgerEntryType::Adjustment])
        ->sum('amount_sen');

    expect($creditedBack)->toBe($without['sale']);
});

test('B5 a partial then full refund reconciles the seller ledger and the payment row', function () {
    [$buyer, $address] = qaCvBuyer();
    $store = qaCvStore(flatFeeSen: 500, commissionRate: 5.0);
    $variant = qaCvVariant($store, 10000);

    app(CartService::class)->addItem($buyer, $variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, null);
    $sub = qaCvComplete($order->subOrders->first(), $buyer);

    $total = (int) $sub->total_sen;              // 10,000 + 500 = 10,500
    $commission = (int) $sub->commission_sen;    // 5% of 10,000 = 500

    expect($total)->toBe(10500)->and($commission)->toBe(500);

    // The real path: refunds are only reachable from a return request
    // (Admin\Orders\Returns::resolve gates on canTransition(..., Refunded)).
    app(SubOrderStatusService::class)->transition($sub, SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);
    $sub = $sub->fresh();

    // Partial: RM40 of RM105.
    app(RefundService::class)->refund($sub, 4000, ActorType::Admin, null);
    $sub = $sub->fresh();

    $expectedReversal = -4000 + intdiv($commission * 4000 + intdiv($total, 2), $total); // −4000 + 190
    $adjustments = (int) $sub->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->sum('amount_sen');

    expect((int) $sub->refunded_sen)->toBe(4000)
        ->and($adjustments)->toBe($expectedReversal)
        ->and($sub->status)->not->toBe(SubOrderStatus::Refunded);

    // Remainder: asking for more than is left must clamp, never overshoot.
    app(RefundService::class)->refund($sub, 99999, ActorType::Admin, null);
    $sub = $sub->fresh();

    expect((int) $sub->refunded_sen)->toBe($total)
        ->and($sub->status)->toBe(SubOrderStatus::Refunded);

    // After a FULL refund the sale and the whole commission must be reversed:
    // sale + commission + adjustments == 0 (COD offset excluded — it is cash).
    $net = (int) $sub->ledgerEntries()
        ->whereIn('type', [LedgerEntryType::Sale, LedgerEntryType::Commission, LedgerEntryType::Adjustment])
        ->sum('amount_sen');

    expect($net)->toBe(0);

    // The buyer can never be refunded more cash than they paid.
    expect((int) $order->fresh()->payment->refunded_sen)->toBeLessThanOrEqual((int) $order->grand_total_sen);
});

test('B6 refunding ONE store of a multi-store order refunds only that store share of the cash', function () {
    [$buyer, $address] = qaCvBuyer();
    $storeA = qaCvStore(flatFeeSen: 0);
    $storeB = qaCvStore(flatFeeSen: 0);
    $variantA = qaCvVariant($storeA, 10000);
    $variantB = qaCvVariant($storeB, 5000);

    qaCvVoucher(['code' => 'PLAT1K', 'type' => VoucherType::Fixed, 'value_sen' => 1000]);

    app(CartService::class)->addItem($buyer, $variantA, 1);
    app(CartService::class)->addItem($buyer, $variantB, 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'PLAT1K');

    expect((int) $order->grand_total_sen)->toBe(14000); // 15,000 − 1,000

    $subA = $order->subOrders->firstWhere('store_id', $storeA->id);
    qaCvComplete($subA, $buyer);
    app(SubOrderStatusService::class)->transition($subA->fresh(), SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);

    // Store A only. The buyer paid 10,000 − (10,000/15,000 of the 1,000
    // platform voucher = 667) = 9,333 of cash toward store A.
    app(RefundService::class)->refund($subA->fresh(), (int) $subA->total_sen, ActorType::Admin, null);

    expect((int) $order->fresh()->payment->refunded_sen)->toBe(9333);
});

test('B7 rounding: no path invents or loses a sen', function () {
    $vouchers = app(VoucherService::class);

    // Largest-remainder proration always sums EXACTLY to the total.
    foreach ([[1000, [1 => 3333, 2 => 3333, 3 => 3334]], [1, [1 => 1, 2 => 1]], [7, [1 => 100, 2 => 3]]] as [$total, $subs]) {
        $shares = $vouchers->prorate($total, $subs);
        expect(array_sum($shares))->toBe($total);
        foreach ($shares as $share) {
            expect($share)->toBeGreaterThanOrEqual(0);
        }
    }

    // Percentage discount on an exact .5 sen: 5% of 1,010 = 50.5 sen.
    $percent = qaCvVoucher(['code' => 'HALFSEN', 'type' => VoucherType::Percent, 'percent' => 5]);
    expect($percent->discountSenFor(1010))->toBe(50); // floors — never over-discounts

    // Tax on an exact .5 sen: 10% of 1,005 = 100.5 → half-up 101.
    $tax = app(TaxService::class);
    $my = $tax->jurisdictionFor('MY');
    expect($tax->lineTaxSen(1005, TaxClass::Standard, true, $my))->toBe(101);

    // Commission on an exact .5 sen: 5% of 1,010 = 50.5 → half-up 51.
    [$buyer, $address] = qaCvBuyer();
    $store = qaCvStore(flatFeeSen: 0, commissionRate: 5.0);
    $variant = qaCvVariant($store, 1010);
    app(CartService::class)->addItem($buyer, $variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $sub = qaCvComplete($order->subOrders->first(), $buyer);

    expect((int) $sub->commission_sen)->toBe(51);

    // A .5-producing quantity does not drift: 3 × 333 = 999, 5% = 49.95 → 50.
    [$buyer2, $address2] = qaCvBuyer();
    $store2 = qaCvStore(flatFeeSen: 0, commissionRate: 5.0);
    $variant2 = qaCvVariant($store2, 333);
    app(CartService::class)->addItem($buyer2, $variant2, 3);
    $order2 = app(CheckoutService::class)->place($buyer2, $address2, PaymentMethod::Cod);
    $sub2 = qaCvComplete($order2->subOrders->first(), $buyer2);

    expect((int) $sub2->commission_sen)->toBe(50);
});

test('B8 VoucherStackingTest line 78 is a missing (int) cast in the TEST, not a product bug', function () {
    [$buyer, $address] = qaCvBuyer();
    $storeA = qaCvStore(flatFeeSen: 700);
    $storeB = qaCvStore(flatFeeSen: 500);
    $variantA = qaCvVariant($storeA, 10000);
    $variantB = qaCvVariant($storeB, 5000);

    $freeShip = qaCvVoucher(['code' => 'QAFREESHIP2', 'type' => VoucherType::FreeShipping, 'quota' => 5]);

    app(CartService::class)->addItem($buyer, $variantA, 1);
    app(CartService::class)->addItem($buyer, $variantB, 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, null, [], 'QAFREESHIP2');

    // The stored VALUE is right — 1,200 sen of waived shipping in one usage row.
    expect((int) $freeShip->usages()->sum('discount_sen'))->toBe(1200)
        ->and($freeShip->usages()->count())->toBe(1)
        ->and((int) $order->shipping_total_sen)->toBe(0);

    // And the driver-dependent part: MariaDB's SUM() comes back as a string,
    // which is what ->toBe(1200) trips over. Pin that it is the type, not the value.
    expect($freeShip->usages()->sum('discount_sen'))->toEqual(1200);
});
