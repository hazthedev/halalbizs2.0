<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['shipping.easyparcel.webhook_token' => 'secret']);
});

function shippedSubOrder(string $awb = 'EP123456'): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 2000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $subOrder = $order->subOrders->first();

    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);

    $subOrder = $subOrder->fresh();
    $subOrder->forceFill(['awb_no' => $awb])->save();

    return $subOrder->fresh();
}

test('a delivered tracking webhook moves a shipped sub-order to delivered and settles COD', function () {
    $subOrder = shippedSubOrder();

    $this->post('/shipping/easyparcel/tracking', [
        'token' => 'secret',
        'awb_no' => 'EP123456',
        'status' => 'delivered',
    ])->assertOk();

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Delivered)
        ->and($subOrder->fresh()->order->payment_status)->toBe(PaymentStatus::Paid);
});

test('a wrong webhook token is rejected', function () {
    shippedSubOrder();

    $this->post('/shipping/easyparcel/tracking', [
        'token' => 'wrong',
        'awb_no' => 'EP123456',
        'status' => 'delivered',
    ])->assertStatus(401);
});

test('an unknown AWB is acknowledged without error', function () {
    $this->post('/shipping/easyparcel/tracking', [
        'token' => 'secret',
        'awb_no' => 'UNKNOWN',
        'status' => 'delivered',
    ])->assertOk();
});

test('a non-delivery event leaves the order shipped', function () {
    $subOrder = shippedSubOrder();

    $this->post('/shipping/easyparcel/tracking', [
        'token' => 'secret',
        'awb_no' => 'EP123456',
        'status' => 'in_transit',
    ])->assertOk();

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Shipped);
});
