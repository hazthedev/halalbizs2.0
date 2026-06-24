<?php

use App\Models\Payment;
use App\Services\Ipay88Service;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\Http;

test('the gateway manager resolves drivers by name', function () {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->driver('ipay88'))->toBeInstanceOf(Ipay88Service::class)
        ->and($manager->driver('stripe'))->toBeInstanceOf(StripeGateway::class)
        ->and($manager->driver('nope'))->toBeNull()
        ->and($manager->driver(null))->toBeNull();
});

test('available gateways respect configuration', function () {
    config(['services.stripe.secret' => null]);
    expect(array_keys(app(PaymentGatewayManager::class)->available()))->toBe(['ipay88']);

    config(['services.stripe.secret' => 'sk_test_x']);
    expect(array_keys(app(PaymentGatewayManager::class)->available()))->toContain('stripe');
});

test('the Stripe driver refunds via the Stripe API when configured', function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200)]);

    $payment = new Payment(['ref_no' => 'ORD-1', 'amount_sen' => 5000]);
    $payment->ipay88_trans_id = 'pi_123';

    expect(app(StripeGateway::class)->refund($payment, 5000, 'REF'))->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.stripe.com/v1/refunds'));
});

test('the Stripe driver is inert without a key', function () {
    config(['services.stripe.secret' => null]);

    expect(app(StripeGateway::class)->refund(new Payment(['ref_no' => 'ORD-1']), 5000))->toBeFalse()
        ->and(app(StripeGateway::class)->createIntent(5000))->toBeNull();
});

test('the checkout method registry exposes the launch rails and gateways', function () {
    $methods = config('payments.methods');

    expect($methods)->toHaveKeys(['cod', 'fpx', 'card', 'card_intl'])
        ->and($methods['fpx']['via'])->toBe('ipay88')
        ->and($methods['card_intl']['via'])->toBe('stripe')
        ->and(config('payments.default_settlement_currency'))->toBe('MYR');
});
