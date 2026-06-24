<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function stockBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

test('checkout writes a Sale stock movement carrying the resulting balance', function () {
    [$buyer, $address] = stockBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $variant, 3);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $movement = StockMovement::where('product_variant_id', $variant->id)
        ->where('type', StockMovementType::Sale)->sole();

    expect($movement->qty_delta)->toBe(-3)
        ->and($movement->balance_after)->toBe(7)
        ->and($movement->reference)->toBe($order->order_no)
        ->and($variant->fresh()->stock)->toBe(7);
});

test('cancelling a sub-order writes a Restock movement back to the original balance', function () {
    [$buyer, $address] = stockBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $variant, 2);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    app(OrderService::class)->cancel($order->subOrders->first(), ActorType::Buyer, $buyer->id, 'Changed mind');

    $restock = StockMovement::where('product_variant_id', $variant->id)
        ->where('type', StockMovementType::Restock)->sole();

    expect($restock->qty_delta)->toBe(2)
        ->and($restock->balance_after)->toBe(10)
        ->and($variant->fresh()->stock)->toBe(10);
});

test('the low-stock scope respects per-variant thresholds and the default', function () {
    $product = Product::factory()->create();
    $variant = $product->variants->first();

    $variant->update(['stock' => 4, 'low_stock_threshold' => null]); // below default 5 → low
    expect(ProductVariant::query()->lowStock()->whereKey($variant->id)->exists())->toBeTrue();

    $variant->update(['stock' => 8, 'low_stock_threshold' => 10]); // below custom 10 → low
    expect(ProductVariant::query()->lowStock()->whereKey($variant->id)->exists())->toBeTrue();

    $variant->update(['stock' => 8, 'low_stock_threshold' => 5]); // above 5 → healthy
    expect(ProductVariant::query()->lowStock()->whereKey($variant->id)->exists())->toBeFalse();
});

test('the packing slip generates a PDF without prices', function () {
    [$buyer, $address] = stockBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $path = app(InvoiceService::class)->packingSlipPath($order->subOrders->first());

    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBeGreaterThan(0);
});
