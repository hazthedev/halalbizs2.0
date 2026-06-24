<?php

use App\Models\Store;
use App\Services\EasyParcelService;
use App\Services\ShippingCalculator;
use Illuminate\Support\Facades\Http;

test('the flat driver returns the store flat fee', function () {
    $store = Store::factory()->create([
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 600,
        'free_shipping_over_sen' => null,
    ]);

    expect(app(ShippingCalculator::class)->feeForStore($store, 'Selangor', 2000))->toBe(600);
});

test('the matrix driver returns the per-state fee, flat fallback for unlisted states', function () {
    $store = Store::factory()->create([
        'shipping_mode' => 'matrix',
        'shipping_matrix' => ['Selangor' => 700, 'Sabah' => 1500],
        'shipping_flat_fee_sen' => 500,
        'free_shipping_over_sen' => null,
    ]);

    $calc = app(ShippingCalculator::class);

    expect($calc->feeForStore($store, 'Selangor', 2000))->toBe(700)
        ->and($calc->feeForStore($store, 'Sabah', 2000))->toBe(1500)
        ->and($calc->feeForStore($store, 'Johor', 2000))->toBe(500);
});

test('the free-shipping threshold zeroes the fee for any mode', function () {
    $store = Store::factory()->create([
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 600,
        'free_shipping_over_sen' => 5000,
    ]);

    $calc = app(ShippingCalculator::class);

    expect($calc->feeForStore($store, 'Selangor', 5000))->toBe(0)
        ->and($calc->feeForStore($store, 'Selangor', 4999))->toBe(600);
});

test('the EasyParcel driver returns the cheapest live rate', function () {
    config(['shipping.easyparcel.enabled' => true, 'shipping.easyparcel.api_key' => 'test']);
    Http::fake(['*/api/v1/rate-checking' => Http::response([
        'result' => [['rates' => [['price' => '7.90'], ['price' => '5.30']]]],
    ])]);

    $store = Store::factory()->create([
        'shipping_mode' => 'easyparcel',
        'shipping_flat_fee_sen' => 999,
        'free_shipping_over_sen' => null,
        'shipping_origin_postcode' => '50000',
    ]);

    expect(app(ShippingCalculator::class)->feeForStore($store, 'Selangor', 2000, '40000', 1200))->toBe(530);
});

test('EasyParcel falls back to the flat fee when unconfigured', function () {
    config(['shipping.easyparcel.enabled' => false]);

    $store = Store::factory()->create([
        'shipping_mode' => 'easyparcel',
        'shipping_flat_fee_sen' => 999,
        'free_shipping_over_sen' => null,
    ]);

    expect(app(ShippingCalculator::class)->feeForStore($store, 'Selangor', 2000, '40000', 1200))->toBe(999);
});

test('decimal price strings parse to sen without floats', function () {
    $service = new EasyParcelService;

    expect($service->priceToSen('5.30'))->toBe(530)
        ->and($service->priceToSen('RM 12.05'))->toBe(1205)
        ->and($service->priceToSen('8'))->toBe(800)
        ->and($service->priceToSen(''))->toBe(0);
});
