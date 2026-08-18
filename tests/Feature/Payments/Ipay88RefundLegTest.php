<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Ipay88Service;
use App\Services\Payments\PaymentGatewayManager;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The gateway refund leg — `Ipay88Service::refund()` and the call to it from
 * RefundService — had never executed anywhere, because it is skipped whenever
 * `services.ipay88.refund_url` is empty, which it is on every environment we
 * have. That was recorded as "blocked on real iPay88 credentials".
 *
 * It is not. Http::fake() drives the whole leg with no merchant account, no
 * money and no network: what we need to know is that we send the right payload,
 * that we read the response correctly, and that a dead endpoint cannot take an
 * order down. None of that needs a real gateway.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M12345';
    $settings->merchant_key = 'test-key';
    $settings->sandbox = true;
    $settings->save();
});

function refundLegPayment(int $amountSen = 25_000): Payment
{
    // Order::factory() rather than Order::create() — the table has required
    // columns (shipping_address among them) the factory already fills.
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'subtotal_sen' => $amountSen,
        'grand_total_sen' => $amountSen,
    ]);

    return Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $order->order_no,
        'amount_sen' => $amountSen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0,
        'paid_at' => now(),
    ]);
}

it('records the refund for the portal instead of calling out when no endpoint is configured', function () {
    config(['services.ipay88.refund_url' => null]);
    Http::fake();

    $payment = refundLegPayment();

    // This is what production does today, and the honest answer is FALSE — the
    // money has not moved, a human still has to refund it in the portal.
    expect(app(Ipay88Service::class)->refund($payment, 10_000, 'REF-1'))->toBeFalse();

    Http::assertNothingSent();
});

it('posts the refund to the configured endpoint with the merchant code, ref and 2dp amount', function () {
    config(['services.ipay88.refund_url' => 'https://payment.ipay88.com.my/refund']);
    Http::fake(['*' => Http::response('SUCCESS', 200)]);

    $payment = refundLegPayment();

    expect(app(Ipay88Service::class)->refund($payment, 125_000, 'REF-2'))->toBeTrue();

    Http::assertSent(function ($request) use ($payment) {
        return $request->url() === 'https://payment.ipay88.com.my/refund'
            && $request['MerchantCode'] === 'M12345'
            && $request['RefNo'] === $payment->ref_no
            // sen → "1250.00": 2dp, no thousand separator. A gateway that is
            // handed "125000" refunds a thousand times too much.
            && $request['Amount'] === '1250.00'
            && ! empty($request['Signature']);
    });
});

it('reports failure rather than success when the gateway rejects the refund', function () {
    config(['services.ipay88.refund_url' => 'https://payment.ipay88.com.my/refund']);
    Http::fake(['*' => Http::response('DENIED', 500)]);

    expect(app(Ipay88Service::class)->refund(refundLegPayment(), 10_000, 'REF-3'))->toBeFalse();
});

it('survives an unreachable gateway instead of throwing into the refund transaction', function () {
    config(['services.ipay88.refund_url' => 'https://payment.ipay88.com.my/refund']);
    Http::fake(fn () => throw new ConnectionException('connection timed out'));

    // A refund runs inside RefundService's DB transaction. An escaping exception
    // here would roll back the seller reversal and the ledger entries too.
    expect(app(Ipay88Service::class)->refund(refundLegPayment(), 10_000, 'REF-4'))->toBeFalse();
});

it('resolves an ipay88 driver at all, which is what makes the refund call reachable', function () {
    // RefundService calls `->driver($method)?->refund(...)`. That `?->` means a
    // rename or a registry change would skip the gateway silently, with no error
    // and no failing test anywhere — the refund would just quietly never happen.
    $driver = app(PaymentGatewayManager::class)->driver(PaymentMethod::Ipay88->value);

    expect($driver)->toBeInstanceOf(Ipay88Service::class);
});
