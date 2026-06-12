<?php

use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Livewire\Storefront\Checkout;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\VoucherService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function voucherTestBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

/** A live COD product with deterministic price, stock, and flat shipping. */
function voucherTestProduct(int $priceSen, ?Store $store = null, int $flatFeeSen = 500): Product
{
    $product = $store === null
        ? Product::factory()->create(['cod_enabled' => true])
        : Product::factory()->for($store)->create(['cod_enabled' => true]);

    $product->store->update([
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => $flatFeeSen,
        'shipping_matrix' => null,
        'free_shipping_over_sen' => null,
    ]);

    $product->variants->first()->update([
        'price_sen' => $priceSen,
        'sale_price_sen' => null,
        'sale_starts_at' => null,
        'sale_ends_at' => null,
        'stock' => 50,
    ]);

    return $product;
}

function voucherTestVoucher(array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Platform,
        'store_id' => null,
        'code' => 'VTEST',
        'type' => VoucherType::Fixed,
        'value_sen' => 500,
        'min_spend_sen' => 0,
        'quota' => null,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ], $attributes));
}

/* ───────────────────────── Proration (largest remainder) ───────────────────────── */

test('proration uses largest remainder so parts sum exactly to the total', function () {
    $service = app(VoucherService::class);

    // The canonical rounding case: thirds never divide evenly.
    expect($service->prorate(1000, [1 => 3333, 2 => 3333, 3 => 3334]))
        ->toBe([1 => 333, 2 => 333, 3 => 334]);

    // A 3-store uneven split — leftover sen go to the largest remainders.
    $shares = $service->prorate(1000, [7 => 5000, 8 => 3000, 9 => 1500]);

    expect($shares)->toBe([7 => 526, 8 => 316, 9 => 158])
        ->and(array_sum($shares))->toBe(1000);
});

test('validate prorates a platform discount across the cart stores', function () {
    [$buyer] = voucherTestBuyer();
    voucherTestVoucher(['code' => 'SPLIT10', 'value_sen' => 1000]);

    $discount = app(VoucherService::class)
        ->validate('SPLIT10', $buyer, [1 => 3333, 2 => 3333, 3 => 3334]);

    expect($discount->scope)->toBe(VoucherScope::Platform)
        ->and($discount->totalDiscountSen)->toBe(1000)
        ->and($discount->perStoreDiscountSen)->toBe([1 => 333, 2 => 333, 3 => 334])
        ->and(array_sum($discount->perStoreDiscountSen))->toBe($discount->totalDiscountSen);
});

/* ───────────────────────── Validation pipeline ───────────────────────── */

test('shop voucher min spend counts only that store\'s subtotal', function () {
    [$buyer] = voucherTestBuyer();
    $store = Store::factory()->approved()->create();

    voucherTestVoucher([
        'scope' => VoucherScope::Shop,
        'store_id' => $store->id,
        'code' => 'SHOPMIN',
        'min_spend_sen' => 5000,
    ]);

    // The ORDER total is RM 140 but the shop's own subtotal is RM 40 — rejected.
    expect(fn () => app(VoucherService::class)->validate('SHOPMIN', $buyer, [$store->id => 4000, 999 => 10000]))
        ->toThrow(CheckoutException::class, "you're RM 10.00 away");

    // Met on the store's own subtotal, regardless of the rest of the cart.
    $discount = app(VoucherService::class)->validate('SHOPMIN', $buyer, [$store->id => 5000, 999 => 100]);

    expect($discount->scope)->toBe(VoucherScope::Shop)
        ->and($discount->totalDiscountSen)->toBe(500)
        ->and($discount->perStoreDiscountSen)->toBe([$store->id => 500]);
});

test('a shop voucher for a store not in the cart is rejected', function () {
    [$buyer] = voucherTestBuyer();
    $otherStore = Store::factory()->approved()->create();

    voucherTestVoucher([
        'scope' => VoucherScope::Shop,
        'store_id' => $otherStore->id,
        'code' => 'ELSEWHERE',
    ]);

    expect(fn () => app(VoucherService::class)->validate('ELSEWHERE', $buyer, [111 => 10000]))
        ->toThrow(CheckoutException::class, 'This voucher belongs to a different shop.');
});

test('a percent voucher is capped at max_discount_sen', function () {
    [$buyer] = voucherTestBuyer();

    voucherTestVoucher([
        'code' => 'TEN-CAPPED',
        'type' => VoucherType::Percent,
        'value_sen' => null,
        'percent' => '10.00',
        'max_discount_sen' => 500,
    ]);

    $discount = app(VoucherService::class)->validate('TEN-CAPPED', $buyer, [1 => 10000]);

    expect($discount->totalDiscountSen)->toBe(500); // 10% of RM 100 = RM 10, capped at RM 5
});

test('the pipeline rejects unknown, inactive, out-of-window, and exhausted vouchers with human reasons', function () {
    [$buyer] = voucherTestBuyer();
    $service = app(VoucherService::class);
    $subtotals = [1 => 10000];

    expect(fn () => $service->validate('GHOST', $buyer, $subtotals))
        ->toThrow(CheckoutException::class, "We can't find that voucher");

    voucherTestVoucher(['code' => 'OFF', 'is_active' => false]);
    expect(fn () => $service->validate('OFF', $buyer, $subtotals))
        ->toThrow(CheckoutException::class, 'no longer active');

    voucherTestVoucher(['code' => 'SOON', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    expect(fn () => $service->validate('SOON', $buyer, $subtotals))
        ->toThrow(CheckoutException::class, "isn't live yet");

    voucherTestVoucher(['code' => 'GONE', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
    expect(fn () => $service->validate('GONE', $buyer, $subtotals))
        ->toThrow(CheckoutException::class, 'expired on');

    voucherTestVoucher(['code' => 'FULL', 'quota' => 1, 'used_count' => 1]);
    expect(fn () => $service->validate('FULL', $buyer, $subtotals))
        ->toThrow(CheckoutException::class, 'fully redeemed');
});

/* ───────────────────────── Checkout integration ───────────────────────── */

test('stacking: a platform and a shop voucher both apply with exact integer totals', function () {
    [$buyer, $address] = voucherTestBuyer();

    $productA = voucherTestProduct(10000, flatFeeSen: 500); // RM 100 + RM 5 ship
    $productB = voucherTestProduct(5000, flatFeeSen: 700);  // RM 50 + RM 7 ship
    $storeA = $productA->store;

    $platform = voucherTestVoucher(['code' => 'PLAT10', 'value_sen' => 1000]);
    $shop = voucherTestVoucher([
        'scope' => VoucherScope::Shop,
        'store_id' => $storeA->id,
        'code' => 'SHOP5',
        'value_sen' => 500,
    ]);

    $cart = app(CartService::class);
    $cart->addItem($buyer, $productA->variants->first(), 1);
    $cart->addItem($buyer, $productB->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'PLAT10', 'SHOP5');

    $subA = $order->subOrders->firstWhere('store_id', $storeA->id);
    $subB = $order->subOrders->firstWhere('store_id', $productB->store_id);

    expect($order->subtotal_sen)->toBe(15000)
        ->and($order->shipping_total_sen)->toBe(1200)
        ->and($order->discount_total_sen)->toBe(1000) // platform share lives at order level
        ->and($subA->shop_discount_sen)->toBe(500)
        ->and($subA->total_sen)->toBe(10000 + 500 - 500)
        ->and($subB->shop_discount_sen)->toBe(0)
        ->and($subB->total_sen)->toBe(5700)
        ->and($order->grand_total_sen)->toBe(14700)
        // Integer-exact: subtotal + shipping − discounts == grand.
        ->and($order->grand_total_sen)->toBe(
            $order->subtotal_sen + $order->shipping_total_sen
            - $order->discount_total_sen - (int) $order->subOrders->sum('shop_discount_sen')
        )
        ->and($order->payment->amount_sen)->toBe($order->grand_total_sen);

    // Both consumed atomically, with usage rows in the same transaction.
    expect($platform->fresh()->used_count)->toBe(1)
        ->and($shop->fresh()->used_count)->toBe(1);

    $platformUsage = $platform->usages()->sole();
    $shopUsage = $shop->usages()->sole();

    expect($platformUsage->order_id)->toBe($order->id)
        ->and($platformUsage->sub_order_id)->toBeNull()
        ->and($platformUsage->discount_sen)->toBe(1000)
        ->and($shopUsage->sub_order_id)->toBe($subA->id)
        ->and($shopUsage->discount_sen)->toBe(500);
});

test('a shop free-shipping voucher zeroes only that store\'s shipping', function () {
    [$buyer, $address] = voucherTestBuyer();

    $productA = voucherTestProduct(10000, flatFeeSen: 500);
    $productB = voucherTestProduct(5000, flatFeeSen: 700);
    $storeA = $productA->store;

    $voucher = voucherTestVoucher([
        'scope' => VoucherScope::Shop,
        'store_id' => $storeA->id,
        'code' => 'SHIPFREE',
        'type' => VoucherType::FreeShipping,
        'value_sen' => null,
    ]);

    $cart = app(CartService::class);
    $cart->addItem($buyer, $productA->variants->first(), 1);
    $cart->addItem($buyer, $productB->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, 'SHIPFREE');

    $subA = $order->subOrders->firstWhere('store_id', $storeA->id);
    $subB = $order->subOrders->firstWhere('store_id', $productB->store_id);

    expect($subA->shipping_fee_sen)->toBe(0)
        ->and($subB->shipping_fee_sen)->toBe(700) // the other store keeps its fee
        ->and($order->shipping_total_sen)->toBe(700)
        ->and($order->discount_total_sen)->toBe(0)
        ->and($order->grand_total_sen)->toBe(15000 + 700);

    $usage = $voucher->usages()->sole();

    expect($usage->sub_order_id)->toBe($subA->id)
        ->and($usage->discount_sen)->toBe(500); // the shipping it waived
});

test('a platform free-shipping voucher zeroes shipping for every store', function () {
    [$buyer, $address] = voucherTestBuyer();

    $productA = voucherTestProduct(10000, flatFeeSen: 500);
    $productB = voucherTestProduct(5000, flatFeeSen: 700);

    $voucher = voucherTestVoucher([
        'code' => 'ALLFREE',
        'type' => VoucherType::FreeShipping,
        'value_sen' => null,
    ]);

    $cart = app(CartService::class);
    $cart->addItem($buyer, $productA->variants->first(), 1);
    $cart->addItem($buyer, $productB->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'ALLFREE');

    expect($order->shipping_total_sen)->toBe(0)
        ->and($order->subOrders->sum('shipping_fee_sen'))->toBe(0)
        ->and($order->grand_total_sen)->toBe(15000)
        ->and($voucher->usages()->sole()->discount_sen)->toBe(1200); // all the shipping it waived
});

test('per-user limit holds across orders even with quota remaining', function () {
    [$buyer, $address] = voucherTestBuyer();
    $product = voucherTestProduct(10000);

    $voucher = voucherTestVoucher(['code' => 'ONEEACH', 'quota' => 100, 'per_user_limit' => 1]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'ONEEACH');

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, 'ONEEACH'))
        ->toThrow(CheckoutException::class, "You've already used this voucher");

    expect($voucher->fresh()->used_count)->toBe(1)
        ->and(Order::count())->toBe(1);
});

/* ───────────────────────── Checkout UI (picker + stacking) ───────────────────────── */

test('checkout UI applies a platform and a shop voucher end to end', function () {
    [$buyer] = voucherTestBuyer();

    $productA = voucherTestProduct(10000, flatFeeSen: 500);
    $productB = voucherTestProduct(5000, flatFeeSen: 700);
    $storeA = $productA->store;

    voucherTestVoucher(['code' => 'PLAT10', 'value_sen' => 1000]);
    $shopVoucher = voucherTestVoucher([
        'scope' => VoucherScope::Shop,
        'store_id' => $storeA->id,
        'code' => 'SHOP5',
        'value_sen' => 500,
    ]);

    $cart = app(CartService::class);
    $cart->addItem($buyer, $productA->variants->first(), 1);
    $cart->addItem($buyer, $productB->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee('RM 162.00') // grand before vouchers: 150 + 12
        // Manual code path for the platform voucher…
        ->set('voucherCode', 'plat10')
        ->call('applyVoucher')
        ->assertSet('appliedPlatformCode', 'PLAT10')
        // …picker path for the shop voucher.
        ->set('voucherPanelOpen', true)
        ->assertSee('SHOP5')
        ->assertSee($storeA->name)
        ->call('selectVoucher', $shopVoucher->id)
        ->assertSet('appliedShopCode', 'SHOP5')
        ->assertSee('Platform voucher')
        ->assertSee('-RM 10.00')
        ->assertSee('Shop voucher')
        ->assertSee('-RM 5.00')
        ->assertSee('RM 147.00') // grand: 162 − 10 − 5
        ->set('paymentMethod', 'cod')
        ->call('placeOrder')
        ->assertRedirect(route('checkout.success', ['order' => Order::first()->order_no]));

    $order = Order::first();
    $subA = $order->subOrders->firstWhere('store_id', $storeA->id);

    expect($order->discount_total_sen)->toBe(1000)
        ->and($subA->shop_discount_sen)->toBe(500)
        ->and($order->grand_total_sen)->toBe(14700)
        ->and($order->grand_total_sen)->toBe(
            $order->subtotal_sen + $order->shipping_total_sen
            - $order->discount_total_sen - (int) $order->subOrders->sum('shop_discount_sen')
        );
});

test('removing an applied voucher restores the totals', function () {
    [$buyer] = voucherTestBuyer();
    $product = voucherTestProduct(10000);
    voucherTestVoucher(['code' => 'PLAT10', 'value_sen' => 1000]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->set('voucherCode', 'PLAT10')
        ->call('applyVoucher')
        ->assertSee('RM 95.00') // 100 + 5 − 10
        ->call('removeVoucher', 'platform')
        ->assertSet('appliedPlatformCode', null)
        ->assertSee('RM 105.00');
});

test('the picker shows an unmet minimum as how far away you are', function () {
    [$buyer] = voucherTestBuyer();
    $product = voucherTestProduct(10000); // RM 100 subtotal
    voucherTestVoucher(['code' => 'BIGMIN', 'min_spend_sen' => 15000]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->set('voucherPanelOpen', true)
        ->assertSee('BIGMIN')
        ->assertSee('Add RM 50.00 more to use this voucher.');
});
