<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('the iPay88 confirmation persists the chosen payment channel', function () {
    Http::fake(['*' => Http::response('00')]); // requery → success

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
    $payment = $order->payment;

    ConfirmIpay88PaymentJob::dispatchSync($payment, [
        'Status' => '1',
        'PaymentId' => '16',   // e.g. FPX Maybank2u channel code
        'TransId' => 'T-CH-1',
        'AuthCode' => 'A1',
    ]);

    $payment->refresh();

    expect($payment->channel)->toBe('16')
        ->and($payment->status)->toBe(GatewayPaymentStatus::Success);
});
