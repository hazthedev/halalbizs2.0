<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Ipay88Service;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestCase;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** @return array{0: User, 1: Address, 2: TestCase} */
function apiCheckoutBuyer(bool $verified = true): array
{
    $user = $verified ? User::factory()->create() : User::factory()->unverified()->create();
    $user->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $user->id, 'state' => 'Selangor']);

    app('auth')->forgetGuards();

    return [$user, $address, test()->withToken($user->createToken('pest-phone')->plainTextToken)];
}

function apiCheckoutCart(User $user, int $qty = 2, int $priceSen = 10000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($user, $product->variants->first(), $qty);

    return $product;
}

function apiCheckoutRealGateway(): void
{
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->sandbox = true;
    $settings->save();
}

function apiCheckoutMockGateway(): void
{
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = '';
    $settings->merchant_key = '';
    $settings->save();
    config(['services.ipay88.allow_mock' => true]);
}

/** @return array<string, string> */
function apiCheckoutBackendPayload(Payment $payment, string $status = '1'): array
{
    return [
        'MerchantCode' => 'M00001',
        'PaymentId' => '2',
        'RefNo' => $payment->ref_no,
        'Amount' => Ipay88Service::formatAmount($payment->amount_sen),
        'Currency' => 'MYR',
        'Remark' => '',
        'TransId' => 'T123456',
        'AuthCode' => 'A1',
        'Status' => $status,
        'ErrDesc' => '',
        'Signature' => app(Ipay88Service::class)->responseSignature('2', $payment->ref_no, $payment->amount_sen, 'MYR', $status),
    ];
}

test('quote has the CheckoutQuote::toArray shape and the same numbers as the service', function () {
    [$user, $address, $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);

    $expected = app(CheckoutService::class)->quote($user, $address, null, null, null, 0, PaymentMethod::Cod)->toArray();

    $http->postJson('/api/v1/checkout/quote', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertOk()
        ->assertJsonPath('data', $expected)
        ->assertJsonPath('data.subtotal_sen', 20000)
        ->assertJsonPath('data.groups.0.lines.0.qty', 2);

    // No address yet: shipping is unknown, the quote still answers.
    $http->postJson('/api/v1/checkout/quote', [])->assertOk()->assertJsonPath('data.groups.0.shipping_fee_sen', null);

    // Someone else's address is a 404.
    $other = Address::factory()->create(['user_id' => User::factory()->create()->id]);
    $http->postJson('/api/v1/checkout/quote', ['address_id' => $other->id])->assertNotFound();
});

test('placing a COD order writes the same totals the quote showed', function () {
    [$user, $address, $http] = apiCheckoutBuyer();
    $product = apiCheckoutCart($user);

    $quote = $http->postJson('/api/v1/checkout/quote', ['address_id' => $address->id, 'payment_method' => 'cod'])->json('data');

    $response = $http->postJson('/api/v1/checkout/place', [
        'address_id' => $address->id,
        'payment_method' => 'cod',
        'seller_notes' => [$product->store_id => 'Leave at the guard house'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'cod')
        ->assertJsonPath('data.total_sen', $quote['grand_total_sen'])
        ->assertJsonPath('data.payment_url', null)
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.sub_orders.0.store.id', $product->store_id)
        ->assertJsonPath('data.sub_orders.0.status', SubOrderStatus::Confirmed->value);

    $order = Order::where('order_no', $response->json('data.order_no'))->firstOrFail();

    expect($order->user_id)->toBe($user->id)
        ->and($order->grand_total_sen)->toBe($quote['grand_total_sen'])
        ->and($order->subtotal_sen)->toBe($quote['subtotal_sen'])
        ->and($order->shipping_total_sen)->toBe($quote['shipping_total_sen'])
        ->and($order->subOrders()->count())->toBe(1)
        ->and($order->subOrders->first()->items->first()->qty)->toBe(2)
        ->and($user->cart->items()->count())->toBe(0);
});

test('a duplicate submit inside a minute returns the same order (cart lock)', function () {
    [$user, $address, $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);

    $payload = ['address_id' => $address->id, 'payment_method' => 'cod'];

    $first = $http->postJson('/api/v1/checkout/place', $payload)->assertCreated()->json('data.order_no');
    $second = $http->postJson('/api/v1/checkout/place', $payload)->assertCreated()->json('data.order_no');

    expect($second)->toBe($first)
        ->and(Order::where('user_id', $user->id)->count())->toBe(1);
});

test('checkout refuses an unverified email with 403 email_unverified', function () {
    [$user, $address, $http] = apiCheckoutBuyer(verified: false);
    apiCheckoutCart($user);

    $http->postJson('/api/v1/checkout/quote', ['address_id' => $address->id])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'email_unverified');

    $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'email_unverified');

    expect(Order::count())->toBe(0);
});

test('an empty cart is a 409 checkout_failed', function () {
    [, $address, $http] = apiCheckoutBuyer();

    $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'checkout_failed');
});

test("placing with someone else's address is a 404 and writes nothing", function () {
    [$user, , $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);
    $other = Address::factory()->create(['user_id' => User::factory()->create()->id]);

    $http->postJson('/api/v1/checkout/place', ['address_id' => $other->id, 'payment_method' => 'cod'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    expect(Order::count())->toBe(0)->and($user->cart->items()->count())->toBe(1);
});

test('a selected line that went out of stock blocks place with the line named', function () {
    [$user, $address, $http] = apiCheckoutBuyer();
    $product = apiCheckoutCart($user);
    $variant = $product->variants->first();
    $variant->update(['stock' => 0]);

    $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'checkout_failed')
        ->assertJsonStructure(['error' => ['fields' => [(string) $variant->id]]]);

    expect(Order::count())->toBe(0);
});

test('an iPay88 order hands off through a signed web URL that renders the entry form without a session', function () {
    apiCheckoutRealGateway();
    [$user, $address, $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);

    $data = $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'ipay88'])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'ipay88')
        ->assertJsonPath('data.sub_orders.0.status', SubOrderStatus::PendingPayment->value)
        ->json('data');

    expect($data['expires_at'])->not->toBeNull()
        ->and($data['payment_url'])->toStartWith(url("/app/pay/{$data['order_no']}"))
        ->and($data['payment_url'])->toContain('signature=');

    $order = Order::where('order_no', $data['order_no'])->firstOrFail();

    // Unauthenticated browser (the app's system browser): the same bridge the web renders.
    $page = $this->get($data['payment_url'])
        ->assertOk()
        ->assertSee('Continue to payment')
        ->assertSee('name="RefNo" value="'.$order->order_no.'"', false)
        ->assertSee('name="BackendURL" value="'.e(route('payments.ipay88.backend')).'"', false)
        ->assertSee(app(Ipay88Service::class)->requestSignature($order->order_no, $order->grand_total_sen));

    // ResponseURL is the app's signed return page, with a 24h expiry.
    preg_match('#name="ResponseURL" value="([^"]+)"#', $page->getContent(), $matches);
    $returnUrl = html_entity_decode($matches[1]);

    expect($returnUrl)->toStartWith(url("/app/pay/{$order->order_no}/return?expires="))
        ->and($returnUrl)->toContain('signature=');
    parse_str((string) parse_url($returnUrl, PHP_URL_QUERY), $query);
    expect((int) $query['expires'])->toBeGreaterThan(now()->addHours(23)->timestamp)->toBeLessThanOrEqual(now()->addDay()->timestamp);

    // The signature is the auth: tampered or expired → 403.
    $this->get($data['payment_url'].'x')->assertForbidden();
    $this->get("/app/pay/{$order->order_no}")->assertForbidden();
    $this->get(URL::temporarySignedRoute('app.pay', now()->subMinute(), ['order' => $order->order_no]))->assertForbidden();

    // The return page writes nothing (hard rule 4), whether iPay88 POSTs it or the simulator GETs it.
    $this->post($returnUrl, ['Status' => '1'])->assertOk()->assertSee('Return to the HalalBizs app')->assertSee("halalbizs://pay/{$order->order_no}");
    $this->get($returnUrl)->assertOk();

    $http->getJson("/api/v1/orders/{$order->order_no}/payment")
        ->assertOk()
        ->assertJsonPath('data.order_no', $order->order_no)
        ->assertJsonPath('data.method', 'ipay88')
        ->assertJsonPath('data.status', PaymentStatus::Pending->value)
        ->assertJsonPath('data.paid', false)
        ->assertJsonStructure(['data' => ['expires_at', 'retry_url']]);

    expect($http->getJson("/api/v1/orders/{$order->order_no}/payment")->json('data.retry_url'))->toContain('signature=');

    // Only the BackendURL callback (+ requery) settles it.
    Http::fake(['*' => Http::response('00')]);
    $this->post('/payments/ipay88/backend', apiCheckoutBackendPayload($order->payment))->assertOk();

    $http->getJson("/api/v1/orders/{$order->order_no}/payment")
        ->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.paid', true)
        ->assertJsonPath('data.retry_url', null);

    expect($order->fresh()->subOrders->first()->status)->toBe(SubOrderStatus::Confirmed);

    // Paid orders get the plain refusal page, not the form.
    $this->get(URL::temporarySignedRoute('app.pay', now()->addMinutes(15), ['order' => $order->order_no]))
        ->assertOk()
        ->assertDontSee('Continue to payment')
        ->assertSee('no longer be paid');

    // Someone else's order number is a 404 on the API.
    [, , $stranger] = apiCheckoutBuyer();
    $stranger->getJson("/api/v1/orders/{$order->order_no}/payment")->assertNotFound();
});

test('in mock mode the signed simulator settles the order exactly like the web simulator', function () {
    apiCheckoutMockGateway();
    [$user, $address, $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);

    $data = $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'ipay88'])->assertCreated()->json('data');
    $order = Order::where('order_no', $data['order_no'])->firstOrFail();

    $page = $this->get($data['payment_url'])->assertOk()->assertSee('Payment simulator');

    preg_match('#action="([^"]+/app/pay/[^"]+/mock/success[^"]*)"#', $page->getContent(), $matches);
    $successUrl = html_entity_decode($matches[1]);

    $this->post($successUrl)->assertRedirect();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment->status)->toBe(GatewayPaymentStatus::Success)
        ->and($order->fresh()->subOrders->first()->status)->toBe(SubOrderStatus::Confirmed);

    $http->getJson("/api/v1/orders/{$order->order_no}/payment")->assertJsonPath('data.paid', true);

    // Replaying the signed simulator POST after settlement writes nothing (hard rule 4).
    $paymentBefore = $order->fresh()->payment->toArray();
    $this->post($successUrl)->assertNotFound();
    expect($order->fresh()->payment->toArray())->toBe($paymentBefore)
        ->and($order->fresh()->payments()->count())->toBe(1);

    // Unsigned simulator POST is refused.
    $this->post("/app/pay/{$order->order_no}/mock/success")->assertForbidden();
});

test('the signed simulator route is a 404 when a real gateway is configured', function () {
    apiCheckoutRealGateway();
    [$user, $address, $http] = apiCheckoutBuyer();
    apiCheckoutCart($user);

    $data = $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'ipay88'])->assertCreated()->json('data');
    $order = Order::where('order_no', $data['order_no'])->firstOrFail();

    $this->post(URL::temporarySignedRoute('app.pay.mock', now()->addMinutes(15), ['order' => $order->order_no, 'result' => 'success']))
        ->assertNotFound();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment->status)->toBe(GatewayPaymentStatus::Pending);
});
