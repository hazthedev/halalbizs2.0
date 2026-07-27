<?php

use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Address;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Adversarial money-loophole audit for CheckoutService::place().
|--------------------------------------------------------------------------
| Each test asserts the SAFE invariant. A PASS = the loophole is guarded
| (REFUTED). A FAIL whose message matches the predicted overflow = a real
| money bug (CONFIRMED). House style: self-contained local helpers, each
| test builds its own rows (RefreshDatabase via tests/Pest.php).
*/

function stressBuyer(): array
{
    Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create([
        'user_id' => $buyer->id,
        'state' => 'Selangor',
        'country' => 'MY',
    ]);

    return [$buyer, $address];
}

/**
 * Approved store on a FLAT shipping fee. free_shipping_over_sen is forced to
 * null because the migration default (RM5.00) would otherwise zero the fee for
 * any subtotal >= 500 sen and make the shipping-bleed tests vacuous. sst_registered
 * stays false so tax is 0 sen (keeps the invariant arithmetic clean).
 */
function stressStore(int $flatFeeSen = 2000, ?float $commissionRate = null): Store
{
    $attrs = [
        'holiday_mode' => false,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => $flatFeeSen,
        'free_shipping_over_sen' => null,
        'sst_registered' => false,
    ];

    if ($commissionRate !== null) {
        $attrs['commission_rate'] = $commissionRate;
    }

    return Store::factory()->approved()->create($attrs);
}

/** Product + its single default variant, priced deterministically (no sale). */
function stressVariant(Store $store, int $priceSen, int $stock = 100): ProductVariant
{
    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants()->first();
    $variant->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => $stock]);

    return $variant->fresh();
}

function stressLine(ProductVariant $variant, int $qty = 1): array
{
    return [['variant_id' => $variant->id, 'qty' => $qty]];
}

function stressPlatformPercent(string $code, int $percent): Voucher
{
    return Voucher::create([
        'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => $code,
        'type' => VoucherType::Percent, 'percent' => $percent, 'value_sen' => 0,
        'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

function stressShopPercent(string $code, int $percent, Store $store): Voucher
{
    return Voucher::create([
        'scope' => VoucherScope::Shop, 'store_id' => $store->id, 'code' => $code,
        'type' => VoucherType::Percent, 'percent' => $percent, 'value_sen' => 0,
        'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

function stressFlashItem(ProductVariant $variant, int $promoSen, int $allocated, int $perBuyerLimit): FlashSaleItem
{
    $sale = FlashSale::create([
        'title' => 'Stress Flash',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    return $sale->items()->create([
        'product_variant_id' => $variant->id,
        'promo_price_sen' => $promoSen,
        'allocated_qty' => $allocated,
        'per_buyer_limit' => $perBuyerLimit,
        'sold_qty' => 0,
    ]);
}

// ---------------------------------------------------------------------------
// H1 — voucher over-discount leak (CheckoutService.php:281-292).
// Platform% is capped to subtotal but shop% is taken raw; both are subtracted
// from subtotal+shipping+tax. With subtotal 1000, shipping 2000 and a 60%+60%
// stack, the combined 1200 item discount exceeds the 1000 subtotal — the 200
// sen overflow eats into shipping the buyer still owes.
// SAFE INVARIANT: grand_total_sen >= shipping_total_sen + tax_total_sen.
// ---------------------------------------------------------------------------
test('H1: item vouchers must never discount shipping or tax', function () {
    [$buyer, $address] = stressBuyer();
    $store = stressStore(flatFeeSen: 2000);
    $variant = stressVariant($store, priceSen: 1000);
    stressPlatformPercent('PLAT60', 60);
    stressShopPercent('SHOP60', 60, $store);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Ipay88, 'PLAT60', 'SHOP60', [], null, 0, stressLine($variant)
    );

    // Guard: the test is only meaningful when a real shipping fee is present.
    expect($order->shipping_total_sen)->toBeGreaterThan(0);

    // SAFE INVARIANT — a fail here means item-scoped vouchers bled into shipping.
    expect($order->grand_total_sen)
        ->toBeGreaterThanOrEqual($order->shipping_total_sen + $order->tax_total_sen);
});

// ---------------------------------------------------------------------------
// H2 — never mint a free order off stacked 100% item vouchers.
// Originally written to prove a grand_total <= 0 FLOOR (expecting a throw).
// PR #34 closed the hole one rung higher: the combined item discount is now
// capped to the items subtotal, so vouchers can zero the goods but can never
// reach through to shipping+tax — the floor is never approached. The order is
// legitimately created and still payable. Assert the cap, which is the rule
// that actually holds the invariant.
// ---------------------------------------------------------------------------
test('H2: platform 100% + shop 100% zeroes the goods but never the shipping', function () {
    [$buyer, $address] = stressBuyer();
    $store = stressStore(flatFeeSen: 2000);
    $variant = stressVariant($store, priceSen: 5000);
    stressPlatformPercent('PLAT100', 100);
    stressShopPercent('SHOP100', 100, $store);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Ipay88, 'PLAT100', 'SHOP100', [], null, 0, stressLine($variant)
    );

    // The two discounts live in different places: the seller-funded shop share on
    // the sub-order, the platform share at order level. The cap makes the PLATFORM
    // share yield to whatever room the shop voucher leaves — here, none.
    $shopDiscountSen = (int) $order->subOrders()->sum('shop_discount_sen');

    expect((int) $order->discount_total_sen)->toBe(0)
        ->and($shopDiscountSen)->toBe(5000)
        // COMBINED, they zero the merchandise exactly — never more.
        ->and((int) $order->discount_total_sen + $shopDiscountSen)->toBe((int) $order->subtotal_sen)
        // ...so the buyer still owes exactly the shipping the vouchers can't touch.
        ->and((int) $order->grand_total_sen)->toBe((int) $order->shipping_total_sen + (int) $order->tax_total_sen)
        // SAFE invariant: no free order was minted.
        ->and((int) $order->grand_total_sen)->toBeGreaterThan(0);

    expect(Order::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// H3 — coins can't exceed payable. A huge wallet + an over-redeem request must
// still leave >= 1 sen payable and never redeem more than the bill.
// PASS = REFUTED/guarded.
// ---------------------------------------------------------------------------
test('H3: coins are capped so at least 1 sen stays payable', function () {
    [$buyer, $address] = stressBuyer();
    $store = stressStore(flatFeeSen: 500);
    $variant = stressVariant($store, priceSen: 3000);

    app(CoinService::class)->credit($buyer, 100000, CoinTransactionType::Adjustment, null, 'stress top-up');
    expect(app(CoinService::class)->balance($buyer))->toBe(100000);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Ipay88, null, null, [], null, 100000, stressLine($variant)
    );

    $preCoin = $order->subtotal_sen + $order->shipping_total_sen + $order->tax_total_sen - $order->discount_total_sen;

    expect($order->grand_total_sen)->toBeGreaterThanOrEqual(1)
        ->and($order->coin_redemption_sen)->toBeLessThanOrEqual($preCoin);
});

// ---------------------------------------------------------------------------
// H4 — flash per_buyer_limit is per-ORDER, not cumulative. With limit=1 and
// allocation 5, two separate qty-1 orders for the SAME buyer both take the
// promo price — the "per buyer" ceiling is bypassed by splitting orders.
// SAFE INVARIANT: the 2nd order should fall back to the normal price.
// A fail (still promo) = CONFIRMED.
// ---------------------------------------------------------------------------
test('H4: flash per_buyer_limit must be cumulative across a buyer\'s orders', function () {
    [$buyer, $address] = stressBuyer();
    $store = stressStore(flatFeeSen: 0);
    $variant = stressVariant($store, priceSen: 10000);
    stressFlashItem($variant, promoSen: 5000, allocated: 5, perBuyerLimit: 1);

    $svc = app(CheckoutService::class);
    $order1 = $svc->place($buyer, $address, PaymentMethod::Ipay88, null, null, [], null, 0, stressLine($variant, 1));
    $order2 = $svc->place($buyer, $address, PaymentMethod::Ipay88, null, null, [], null, 0, stressLine($variant, 1));

    // Guard: the first order genuinely took the flash promo price.
    expect($order1->subOrders->first()->items->first()->unit_price_sen)->toBe(5000);

    // SAFE INVARIANT — a fail (still 5000) means the limit is per-order only.
    expect($order2->subOrders->first()->items->first()->unit_price_sen)->toBe(10000);
});

// ---------------------------------------------------------------------------
// H5 — flash sold_qty not released on cancel. Cancelling restocks inventory but
// (per OrderService::cancel) never touches the flash allocation, so sold_qty
// stays inflated and the deal exhausts even though nothing sold.
// SAFE INVARIANT: sold_qty returns to 0 after cancel. A fail = CONFIRMED leak.
// ---------------------------------------------------------------------------
test('H5: flash sold_qty must be released when a sub-order is cancelled', function () {
    [$buyer, $address] = stressBuyer();
    $store = stressStore(flatFeeSen: 0);
    $variant = stressVariant($store, priceSen: 10000);
    $flash = stressFlashItem($variant, promoSen: 5000, allocated: 10, perBuyerLimit: 5);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Ipay88, null, null, [], null, 0, stressLine($variant, 2)
    );

    // Guard: flash engaged and consumed 2 units of allocation.
    expect($order->subOrders->first()->items->first()->unit_price_sen)->toBe(5000)
        ->and($flash->fresh()->sold_qty)->toBe(2);

    app(OrderService::class)->cancel($order->subOrders->first(), ActorType::System);

    // SAFE INVARIANT — a fail (still 2) means allocation is permanently burned.
    expect($flash->fresh()->sold_qty)->toBe(0);
});

// ---------------------------------------------------------------------------
// H6 — commission basis (design flag, no hard verdict). Commission is charged
// on the GROSS items_subtotal, ignoring the shop discount the seller granted.
// Documented here with the actual numbers: gross 10000 @ 10% = 1000, whereas a
// net-of-shop-discount basis (net 4000) would charge only 400.
// ---------------------------------------------------------------------------
// H6 is RESOLVED and removed. It was a design flag, not a verdict — it recorded
// that commission was charged on the gross items_subtotal and noted a net basis
// would charge 400 instead of 1000. Haze's call (2026-07-27) made the basis a
// panel setting with both options, defaulting to NET, so a single hard-coded
// expectation is no longer the truth.
//
// Its replacement is tests/Feature/Ledger/CommissionBasisTest.php, which pins
// both bases, proves the two are genuinely different, and — the thing H6 could
// not see — proves that a flash sale and a shop voucher of the same value now
// cost the seller the same fee under either basis. They did not before.
