<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderExpiredNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Ipay88Service;
use App\Services\SubOrderStatusService;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->sandbox = true;
    $settings->save();
});

function placeIpay88Order(): Order
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);

    return app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
}

function backendPayload(Payment $payment, string $status = '1', string $transId = 'T123456'): array
{
    $service = app(Ipay88Service::class);

    return [
        'MerchantCode' => 'M00001',
        'PaymentId' => '2',
        'RefNo' => $payment->ref_no,
        'Amount' => Ipay88Service::formatAmount($payment->amount_sen),
        'Currency' => 'MYR',
        'Remark' => '',
        'TransId' => $transId,
        'AuthCode' => 'A1',
        'Status' => $status,
        'ErrDesc' => $status === '1' ? '' : 'Customer cancelled',
        'Signature' => $service->responseSignature('2', $payment->ref_no, $payment->amount_sen, 'MYR', $status),
    ];
}

test('signatures follow the documented sha256 formulas', function () {
    $service = app(Ipay88Service::class);

    // sha256(key . code . refNo . amountNoSeparators . currency)
    expect($service->requestSignature('MP2606ABC123', 125000, 'MYR'))
        ->toBe(hash('sha256', 'TestKey123M00001MP2606ABC123125000MYR'));

    // Response adds PaymentId and Status.
    expect($service->responseSignature('2', 'MP2606ABC123', 125000, 'MYR', '1'))
        ->toBe(hash('sha256', 'TestKey123M00001'.'2'.'MP2606ABC123'.'125000'.'MYR'.'1'));

    expect(Ipay88Service::formatAmount(125000))->toBe('1250.00')
        ->and(Ipay88Service::formatAmount(50))->toBe('0.50')
        ->and(Ipay88Service::amountToSen('1,250.00'))->toBe(125000)
        ->and(Ipay88Service::amountToSen('0.50'))->toBe(50);
});

test('backend callback with valid signature and 00 requery fulfils the order', function () {
    Http::fake(['*' => Http::response('00')]);

    $order = placeIpay88Order();
    $payment = $order->payment;

    $response = $this->post('/payments/ipay88/backend', backendPayload($payment));

    $response->assertOk();
    expect($response->getContent())->toBe('RECEIVEOK');

    $payment = $payment->fresh();
    expect($payment->status)->toBe(GatewayPaymentStatus::Success)
        ->and($payment->signature_valid)->toBeTrue()
        ->and($payment->ipay88_trans_id)->toBe('T123456')
        ->and($payment->requery_result)->toBe('00');

    $order = $order->fresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->expires_at)->toBeNull()
        ->and($order->subOrders->first()->status)->toBe(SubOrderStatus::Confirmed);
});

test('replayed callback with the same TransId is idempotent', function () {
    Http::fake(['*' => Http::response('00')]);

    $order = placeIpay88Order();
    $payment = $order->payment;

    $this->post('/payments/ipay88/backend', backendPayload($payment))->assertOk();
    $paidAt = $payment->fresh()->paid_at;

    // Replay — must ack without side effects (no duplicate transitions).
    $this->post('/payments/ipay88/backend', backendPayload($payment))->assertOk();

    expect($payment->fresh()->paid_at->toIso8601String())->toBe($paidAt->toIso8601String())
        ->and($order->fresh()->subOrders->first()->statusHistories()->count())->toBe(2); // initial + confirmed once
});

test('invalid signature is flagged and never fulfils', function () {
    Http::fake(['*' => Http::response('00')]);

    $order = placeIpay88Order();
    $payment = $order->payment;

    $payload = backendPayload($payment);
    $payload['Signature'] = 'tampered';

    $this->post('/payments/ipay88/backend', $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Pending)
        ->and($payment->fresh()->signature_valid)->toBeFalse()
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('failed status marks the payment failed without touching sub-orders', function () {
    $order = placeIpay88Order();
    $payment = $order->payment;

    $this->post('/payments/ipay88/backend', backendPayload($payment, status: '0'))->assertOk();

    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Failed)
        ->and($order->fresh()->subOrders->first()->status)->toBe(SubOrderStatus::PendingPayment);
});

test('requery mismatch leaves the payment pending for admin review', function () {
    Http::fake(['*' => Http::response('Record not found')]);

    $order = placeIpay88Order();
    $payment = $order->payment;

    $this->post('/payments/ipay88/backend', backendPayload($payment))->assertOk();

    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Pending)
        ->and($payment->fresh()->requery_result)->toBe('Record not found')
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('bridge page renders the auto-submit form with a valid signature', function () {
    $order = placeIpay88Order();

    $response = $this->actingAs($order->user)->get("/pay/{$order->order_no}");

    $response->assertOk()
        ->assertSee('Continue to payment')
        ->assertSee($order->order_no)
        ->assertSee(app(Ipay88Service::class)->requestSignature($order->order_no, $order->grand_total_sen));

    // Stranger blocked.
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get("/pay/{$order->order_no}")->assertForbidden();
});

test('pay-again after a failed attempt uses a fresh RefNo suffix', function () {
    $order = placeIpay88Order();
    $payment = $order->payment;

    $this->post('/payments/ipay88/backend', backendPayload($payment, status: '0'))->assertOk();
    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Failed);

    $response = $this->actingAs($order->user)->get("/pay/{$order->order_no}");

    $response->assertOk()->assertSee("{$order->order_no}-2");
    expect($order->payments()->count())->toBe(2);
});

test('expiry command cancels unpaid orders, restocks, and notifies', function () {
    Notification::fake();
    Http::fake(['*' => Http::response('Record not found')]);

    $order = placeIpay88Order();
    $variant = $order->subOrders->first()->items->first()->variant;
    expect($variant->fresh()->stock)->toBe(3);

    $order->update(['expires_at' => now()->subMinute()]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Expired)
        ->and($order->fresh()->subOrders->first()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($variant->fresh()->stock)->toBe(5);

    Notification::assertSentTo($order->user, OrderExpiredNotification::class);
});

test('expiry command rescues a late payment via requery', function () {
    Http::fake(['*' => Http::response('00')]);

    $order = placeIpay88Order();
    $order->update(['expires_at' => now()->subMinute()]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->subOrders->first()->status)->toBe(SubOrderStatus::Confirmed);
});

test('auto-complete command completes delivered sub-orders past the window', function () {
    $order = placeIpay88Order();
    $subOrder = $order->subOrders->first();

    // Walk to delivered via the service, then age the window.
    $statusService = app(SubOrderStatusService::class);
    $statusService->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
    $statusService->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $statusService->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    $statusService->transition($subOrder->fresh(), SubOrderStatus::Delivered, ActorType::System);

    $subOrder->fresh()->forceFill(['auto_complete_at' => now()->subHour()])->save();

    $this->artisan('orders:auto-complete')->assertSuccessful();

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Completed)
        ->and($subOrder->fresh()->completed_at)->not->toBeNull();
});
