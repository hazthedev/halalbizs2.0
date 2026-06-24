<?php

use App\Enums\PaymentMethod;
use App\Livewire\Admin\Content\FlashSales;
use App\Models\Address;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\FlashSaleService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function flashBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function liveFlash(ProductVariant $variant, int $promoSen, int $alloc, int $perBuyer = 5, int $sold = 0): FlashSaleItem
{
    $sale = FlashSale::create([
        'title' => '11.11', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true,
    ]);

    return FlashSaleItem::create([
        'flash_sale_id' => $sale->id, 'product_variant_id' => $variant->id,
        'promo_price_sen' => $promoSen, 'allocated_qty' => $alloc, 'per_buyer_limit' => $perBuyer, 'sold_qty' => $sold,
    ]);
}

function flashProduct(int $priceSen = 10000): ProductVariant
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 50]);

    return $variant;
}

test('a live flash deal prices the line at the promo and consumes allocation', function () {
    [$buyer, $address] = flashBuyer();
    $variant = flashProduct(10000);
    $item = liveFlash($variant, 6000, 10, perBuyer: 5);

    app(CartService::class)->addItem($buyer, $variant, 2);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $oi = $order->subOrders->first()->items->first();

    expect($oi->unit_price_sen)->toBe(6000)
        ->and($oi->line_total_sen)->toBe(12000)
        ->and($order->subtotal_sen)->toBe(12000)
        ->and($item->fresh()->sold_qty)->toBe(2);
});

test('once allocation is exhausted the line reverts to the normal price (no oversell)', function () {
    [$buyer, $address] = flashBuyer();
    $variant = flashProduct(10000);
    $item = liveFlash($variant, 6000, 2, perBuyer: 5, sold: 2); // fully claimed

    app(CartService::class)->addItem($buyer, $variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->subOrders->first()->items->first()->unit_price_sen)->toBe(10000)
        ->and($item->fresh()->sold_qty)->toBe(2); // unchanged
});

test('a line over the per-buyer limit pays the normal price and consumes no allocation', function () {
    [$buyer, $address] = flashBuyer();
    $variant = flashProduct(10000);
    $item = liveFlash($variant, 6000, 50, perBuyer: 1);

    app(CartService::class)->addItem($buyer, $variant, 2); // exceeds per-buyer limit of 1
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->subOrders->first()->items->first()->unit_price_sen)->toBe(10000)
        ->and($item->fresh()->sold_qty)->toBe(0);
});

test('an ended flash sale does not apply', function () {
    [$buyer, $address] = flashBuyer();
    $variant = flashProduct(10000);

    $sale = FlashSale::create(['title' => 'Past', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(), 'is_active' => true]);
    FlashSaleItem::create([
        'flash_sale_id' => $sale->id, 'product_variant_id' => $variant->id,
        'promo_price_sen' => 6000, 'allocated_qty' => 10, 'per_buyer_limit' => 5, 'sold_qty' => 0,
    ]);

    app(CartService::class)->addItem($buyer, $variant, 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->subOrders->first()->items->first()->unit_price_sen)->toBe(10000);
});

test('the storefront flash-sale page lists live deals with promo prices', function () {
    $variant = flashProduct(10000);
    liveFlash($variant, 6000, 10);

    $this->get(route('flash-sale'))
        ->assertOk()
        ->assertSee('60.00')  // promo RM60.00
        ->assertSee('100.00'); // struck-through original
});

test('an admin can create a flash sale and add a deal line', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $variant = flashProduct(10000);

    Livewire\Livewire::actingAs($admin)
        ->test(FlashSales::class)
        ->set('title', '11.11')
        ->set('startsAt', now()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('createSale')
        ->assertHasNoErrors();

    $sale = FlashSale::firstOrFail();

    Livewire\Livewire::actingAs($admin)
        ->test(FlashSales::class)
        ->call('openAddItem', $sale->id)
        ->set('itemVariantId', (string) $variant->id)
        ->set('itemPromo', '60')
        ->set('itemAllocated', 20)
        ->set('itemPerBuyer', 2)
        ->call('addItem')
        ->assertHasNoErrors();

    expect(FlashSaleItem::where('flash_sale_id', $sale->id)->where('product_variant_id', $variant->id)->first())
        ->not->toBeNull()
        ->promo_price_sen->toBe(6000)
        ->allocated_qty->toBe(20);
});

test('FlashSaleService resolves the promo price only while live with allocation', function () {
    $variant = flashProduct(10000);
    $item = liveFlash($variant, 6000, 1, perBuyer: 5);

    expect(app(FlashSaleService::class)->priceFor($variant))->toBe(6000);

    $item->update(['sold_qty' => 1]); // exhausted
    expect(app(FlashSaleService::class)->priceFor($variant->fresh()))->toBe(10000);
});
