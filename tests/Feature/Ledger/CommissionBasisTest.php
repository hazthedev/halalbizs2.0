<?php

/**
 * Commission basis (docs/09 §A) — GROSS vs NET, selectable from the admin panel.
 *
 * The rule these tests pin down: only SELLER-funded discounts move the base.
 * Platform vouchers never do, because the platform absorbs them and the seller
 * is paid in full.
 *
 * The bug that motivated the setting: the old code charged on
 * items_subtotal_sen, which is built AFTER a flash-sale price cut but BEFORE a
 * shop voucher — so the same RM20 given away by the seller produced a different
 * fee depending on which mechanism they used. Under either basis here, the two
 * mechanisms now agree.
 */

use App\Enums\ActorType;
use App\Enums\CommissionBasis;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Admin\Finance\Commission;
use App\Models\Address;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use App\Settings\CommissionSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => test()->seed(RoleSeeder::class));

function useBasis(CommissionBasis $basis): void
{
    $settings = app(CommissionSettings::class);
    $settings->discount_basis = $basis->value;
    $settings->save();
}

function basisStore(float $commissionRate = 5.0): Store
{
    return Store::factory()->approved()->create([
        'holiday_mode' => false,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 0, // keep the arithmetic about goods only
        'free_shipping_over_sen' => null,
        'sst_registered' => false,
        'commission_rate' => $commissionRate,
    ]);
}

function basisVariant(Store $store, int $priceSen): ProductVariant
{
    $product = Product::factory()->create(['store_id' => $store->id, 'cod_enabled' => true]);
    $variant = $product->variants()->first();
    $variant->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 100]);

    return $variant->fresh();
}

function basisFlash(ProductVariant $variant, int $promoPriceSen): void
{
    $sale = FlashSale::create([
        'title' => 'Basis flash',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $sale->items()->create([
        'product_variant_id' => $variant->id,
        'promo_price_sen' => $promoPriceSen,
        'allocated_qty' => 50,
        'per_buyer_limit' => 50,
        'sold_qty' => 0,
    ]);
}

function basisShopVoucher(Store $store, string $code, int $percent): Voucher
{
    return Voucher::create([
        'scope' => VoucherScope::Shop, 'store_id' => $store->id, 'code' => $code,
        'type' => VoucherType::Percent, 'percent' => $percent, 'value_sen' => 0,
        'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
}

/** Place an order and walk it all the way to Completed, which is what books commission. */
function completeWith(ProductVariant $variant, ?string $shopCode = null, ?string $platformCode = null): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create([
        'user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY',
    ]);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Cod, $platformCode, $shopCode, [], null, 0,
        [['variant_id' => $variant->id, 'qty' => 1]],
    );

    $subOrder = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);

    return $subOrder->fresh();
}

// ── The worked example that decided the design ─────────────────────────────
// RM100 of goods, the seller gives away RM20 of their own money, 5% rate.

test('NET charges the same whether the seller discounts by flash sale or by voucher', function () {
    useBasis(CommissionBasis::Net);

    $viaFlash = basisStore();
    $flashVariant = basisVariant($viaFlash, 10000);
    basisFlash($flashVariant, 8000);

    $viaVoucher = basisStore();
    $voucherVariant = basisVariant($viaVoucher, 10000);
    basisShopVoucher($viaVoucher, 'SHOP20', 20);

    $flashSub = completeWith($flashVariant);
    $voucherSub = completeWith($voucherVariant, shopCode: 'SHOP20');

    // 5% of the RM80 the seller actually received, both ways.
    expect((int) $flashSub->commission_sen)->toBe(400)
        ->and((int) $voucherSub->commission_sen)->toBe(400);
});

test('GROSS charges the same whether the seller discounts by flash sale or by voucher', function () {
    useBasis(CommissionBasis::Gross);

    $viaFlash = basisStore();
    $flashVariant = basisVariant($viaFlash, 10000);
    basisFlash($flashVariant, 8000);

    $viaVoucher = basisStore();
    $voucherVariant = basisVariant($viaVoucher, 10000);
    basisShopVoucher($viaVoucher, 'SHOP20', 20);

    $flashSub = completeWith($flashVariant);
    $voucherSub = completeWith($voucherVariant, shopCode: 'SHOP20');

    // 5% of the RM100 list price, both ways — the discount is the seller's spend.
    expect((int) $flashSub->commission_sen)->toBe(500)
        ->and((int) $voucherSub->commission_sen)->toBe(500);
});

test('the two bases genuinely differ — this is a real choice, not a no-op', function () {
    $build = function (CommissionBasis $basis): int {
        useBasis($basis);
        $store = basisStore();
        $variant = basisVariant($store, 10000);
        basisShopVoucher($store, 'SHOP20-'.strtoupper($basis->value), 20);

        return (int) completeWith($variant, shopCode: 'SHOP20-'.strtoupper($basis->value))->commission_sen;
    };

    expect($build(CommissionBasis::Net))->toBe(400)
        ->and($build(CommissionBasis::Gross))->toBe(500);
});

// ── Platform vouchers are not the seller's discount ────────────────────────

test('a platform-funded voucher never reduces the base, under either basis', function () {
    foreach ([CommissionBasis::Net, CommissionBasis::Gross] as $basis) {
        useBasis($basis);

        $store = basisStore();
        $variant = basisVariant($store, 10000);
        Voucher::create([
            'scope' => VoucherScope::Platform, 'store_id' => null, 'code' => 'PLAT20-'.strtoupper($basis->value),
            'type' => VoucherType::Percent, 'percent' => 20, 'value_sen' => 0,
            'min_spend_sen' => 0, 'quota' => null, 'per_user_limit' => 10, 'used_count' => 0,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
        ]);

        $subOrder = completeWith($variant, platformCode: 'PLAT20-'.strtoupper($basis->value));

        // The platform ate the RM20; the seller was paid RM100, so 5% of RM100.
        expect((int) $subOrder->commission_sen)->toBe(500)
            ->and((int) $subOrder->shop_discount_sen)->toBe(0);
    }
});

// ── The snapshots, without which history is unexplainable ──────────────────

test('the basis that produced a commission is snapshotted on the sub-order', function () {
    useBasis(CommissionBasis::Gross);
    $store = basisStore();
    $subOrder = completeWith(basisVariant($store, 10000));

    expect($subOrder->commission_basis)->toBe(CommissionBasis::Gross);

    // Flipping the setting afterwards must NOT rewrite settled history.
    useBasis(CommissionBasis::Net);

    expect($subOrder->fresh()->commission_basis)->toBe(CommissionBasis::Gross)
        ->and((int) $subOrder->fresh()->commission_sen)->toBe(500);
});

test('checkout snapshots the pre-campaign list price so GROSS has a base', function () {
    useBasis(CommissionBasis::Gross);

    $store = basisStore();
    $variant = basisVariant($store, 10000);
    basisFlash($variant, 8000);

    $item = completeWith($variant)->items->first();

    // Paid price is the promo; list price is what it would have sold at.
    expect((int) $item->unit_price_sen)->toBe(8000)
        ->and((int) $item->list_price_sen)->toBe(10000);
});

test('GROSS falls back to the paid price on legacy lines with no snapshot', function () {
    useBasis(CommissionBasis::Gross);

    $store = basisStore();
    $subOrder = completeWith(basisVariant($store, 10000));

    // Simulate a pre-migration row, then re-book from scratch.
    $subOrder->items()->update(['list_price_sen' => null]);
    $subOrder->ledgerEntries()->delete();
    $subOrder->forceFill(['commission_sen' => null, 'commission_basis' => null])->save();

    app(LedgerService::class)->recordCompletion($subOrder->fresh());

    // No invented price — charged on what the line actually recorded.
    expect((int) $subOrder->fresh()->commission_sen)->toBe(500);
});

// ── The panel toggle ───────────────────────────────────────────────────────

test('a superadmin can switch the basis from the Commission screen', function () {
    useBasis(CommissionBasis::Net);

    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->forceFill(['is_superadmin' => true])->save();

    Livewire::actingAs($admin->fresh())
        ->test(Commission::class)
        ->assertSet('discountBasis', CommissionBasis::Net->value)
        ->set('discountBasis', CommissionBasis::Gross->value)
        ->call('saveDiscountBasis')
        ->assertHasNoErrors();

    expect(app(CommissionSettings::class)->refresh()->basis())->toBe(CommissionBasis::Gross);
});

test('the basis setting rejects a value that is not a known basis', function () {
    useBasis(CommissionBasis::Net);

    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->forceFill(['is_superadmin' => true])->save();

    Livewire::actingAs($admin->fresh())
        ->test(Commission::class)
        ->set('discountBasis', 'whatever-i-like')
        ->call('saveDiscountBasis')
        ->assertHasErrors('discountBasis');

    expect(app(CommissionSettings::class)->refresh()->basis())->toBe(CommissionBasis::Net);
});

// ── The ledger still balances ──────────────────────────────────────────────

test('the commission ledger entry matches the stamped commission under both bases', function () {
    foreach ([CommissionBasis::Net, CommissionBasis::Gross] as $basis) {
        useBasis($basis);

        $store = basisStore();
        $variant = basisVariant($store, 10000);
        basisShopVoucher($store, 'SHOP20-L-'.strtoupper($basis->value), 20);
        $subOrder = completeWith($variant, shopCode: 'SHOP20-L-'.strtoupper($basis->value));

        $entry = $store->ledgerEntries()
            ->where('type', LedgerEntryType::Commission)
            ->where('sub_order_id', $subOrder->id)
            ->first();

        expect((int) $entry->amount_sen)->toBe(-(int) $subOrder->commission_sen);
    }
});
