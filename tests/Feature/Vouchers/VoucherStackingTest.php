<?php

use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function stackBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

test('a platform discount, a free-shipping voucher and a shop voucher all stack', function () {
    [$buyer, $address] = stackBuyer();

    $productA = Product::factory()->create(['cod_enabled' => true]);
    $productB = Product::factory()->create(['cod_enabled' => true]); // different store
    $productA->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 700, 'free_shipping_over_sen' => null]);
    $productB->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 500, 'free_shipping_over_sen' => null]);
    $productA->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);
    $productB->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 10]);

    $platform = Voucher::create([
        'scope' => VoucherScope::Platform, 'code' => 'PLAT10', 'type' => VoucherType::Fixed, 'value_sen' => 1000,
        'quota' => 5, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
    $freeShip = Voucher::create([
        'scope' => VoucherScope::Platform, 'code' => 'FREESHIP', 'type' => VoucherType::FreeShipping,
        'quota' => 5, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
    $shop = Voucher::create([
        'scope' => VoucherScope::Shop, 'store_id' => $productA->store_id, 'code' => 'SHOP5', 'type' => VoucherType::Fixed, 'value_sen' => 500,
        'quota' => 5, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    app(CartService::class)->addItem($buyer, $productA->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $productB->variants->first(), 1);

    $order = app(CheckoutService::class)->place(
        $buyer, $address, PaymentMethod::Cod, 'PLAT10', 'SHOP5', [], 'FREESHIP',
    );

    $subA = $order->subOrders->firstWhere('store_id', $productA->store_id);
    $subB = $order->subOrders->firstWhere('store_id', $productB->store_id);

    expect($order->subtotal_sen)->toBe(15000)
        ->and($order->shipping_total_sen)->toBe(0)              // free-shipping waived both
        ->and($order->discount_total_sen)->toBe(1000)           // platform discount
        ->and($subA->shop_discount_sen)->toBe(500)              // shop voucher on store A
        ->and($subB->shop_discount_sen)->toBe(0)
        ->and($subA->shipping_subsidy_sen)->toBe(700)           // waived shipping recorded
        ->and($subB->shipping_subsidy_sen)->toBe(500)
        ->and($order->grand_total_sen)->toBe(15000 - 1000 - 500); // 13,500

    // Reconciliation: grand = subtotal + shipping − platform − shop.
    expect($order->grand_total_sen)->toBe(
        $order->subtotal_sen + $order->shipping_total_sen
        - $order->discount_total_sen - (int) $order->subOrders->sum('shop_discount_sen'),
    );

    // All three vouchers consumed once.
    expect($platform->fresh()->used_count)->toBe(1)
        ->and($freeShip->fresh()->used_count)->toBe(1)
        ->and($shop->fresh()->used_count)->toBe(1)
        ->and($freeShip->usages()->sum('discount_sen'))->toBe(1200); // total waived shipping
});

test('a non-free-shipping voucher is rejected from the free-shipping slot', function () {
    [$buyer, $address] = stackBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 10]);

    Voucher::create([
        'scope' => VoucherScope::Platform, 'code' => 'NOTSHIP', 'type' => VoucherType::Fixed, 'value_sen' => 500,
        'quota' => 5, 'per_user_limit' => 1, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod, null, null, [], 'NOTSHIP'))
        ->toThrow(CheckoutException::class, 'not a free-shipping voucher');
});
