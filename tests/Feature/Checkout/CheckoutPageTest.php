<?php

use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Storefront\Checkout;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** A verified buyer with a default Selangor address. */
function checkoutPageBuyer(string $state = 'Selangor'): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => $state]);

    return [$buyer, $address];
}

/** A live product with a deterministic price, stock, and flat shipping fee. */
function checkoutPageProduct(int $priceSen, int $stock = 10, bool $cod = true, int $flatFeeSen = 500): Product
{
    $product = Product::factory()->create(['cod_enabled' => $cod]);

    $product->store->update([
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => $flatFeeSen,
        'shipping_matrix' => null,
        'free_shipping_over_sen' => null,
    ]);

    $product->variants->first()->update([
        'price_sen' => $priceSen,
        'sale_price_sen' => null,
        'sale_starts_at' => null,
        'sale_ends_at' => null,
        'stock' => $stock,
    ]);

    return $product;
}

function checkoutPagePlatformVoucher(string $code, int $valueSen, int $minSpendSen = 0): Voucher
{
    return Voucher::create([
        'scope' => VoucherScope::Platform,
        'code' => $code,
        'type' => VoucherType::Fixed,
        'value_sen' => $valueSen,
        'min_spend_sen' => $minSpendSen,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);
}

test('a guest is redirected to login', function () {
    $this->get(route('checkout'))->assertRedirect(route('login'));
});

test('a buyer with nothing selected is sent back to the cart', function () {
    [$buyer] = checkoutPageBuyer();

    $this->actingAs($buyer)->get(route('checkout'))->assertRedirect(route('cart'));
});

test('a buyer with selected items sees seller groups, shipping fees, and totals', function () {
    [$buyer] = checkoutPageBuyer();
    $productA = checkoutPageProduct(10000, flatFeeSen: 500);  // RM 100.00, ship RM 5.00
    $productB = checkoutPageProduct(2500, flatFeeSen: 700);   // RM 25.00 × 2, ship RM 7.00

    $service = app(CartService::class);
    $service->addItem($buyer, $productA->variants->first(), 1);
    $service->addItem($buyer, $productB->variants->first(), 2);

    $this->actingAs($buyer)->get(route('checkout'))->assertOk();

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee($productA->store->name)
        ->assertSee($productB->store->name)
        ->assertSee('RM 5.00')      // store A shipping
        ->assertSee('RM 7.00')      // store B shipping
        ->assertSee('RM 150.00')    // items subtotal
        ->assertSee('RM 12.00')     // shipping total
        ->assertSee('RM 162.00');   // grand total
});

test('changing address recomputes shipping from the state matrix', function () {
    [$buyer, $selangor] = checkoutPageBuyer('Selangor');
    $sabah = Address::factory()->create(['user_id' => $buyer->id, 'state' => 'Sabah']);

    $product = checkoutPageProduct(2000);
    $product->store->update([
        'shipping_mode' => 'matrix',
        'shipping_matrix' => ['Selangor' => 700, 'Sabah' => 1500],
        'shipping_flat_fee_sen' => 500,
    ]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSet('addressId', $selangor->id)
        ->assertSee('RM 7.00')
        ->call('selectAddress', $sabah->id)
        ->assertSet('addressId', $sabah->id)
        ->assertDontSee('RM 7.00')
        ->assertSee('RM 15.00')
        ->assertSee('RM 35.00'); // grand total RM 20.00 + RM 15.00
});

test('applying a platform voucher shows the discount and lower total', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000); // RM 100.00 + RM 5.00 shipping
    checkoutPagePlatformVoucher('SAVE5', 500);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee('RM 105.00')
        ->set('voucherCode', 'SAVE5')
        ->call('applyVoucher')
        ->assertSet('appliedVoucherCode', 'SAVE5')
        ->assertSee('SAVE5')
        ->assertSee('-RM 5.00')
        ->assertSee('RM 100.00'); // grand total after discount
});

test('an invalid voucher code shows a human error and no discount', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->set('voucherCode', 'NOPE')
        ->call('applyVoucher')
        ->assertSet('appliedVoucherCode', null)
        ->assertSee("We can't find that voucher");
});

test('a voucher below its minimum spend says how far away you are', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000); // RM 100.00 subtotal
    checkoutPagePlatformVoucher('MIN150', 500, minSpendSen: 15000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->set('voucherCode', 'MIN150')
        ->call('applyVoucher')
        ->assertSet('appliedVoucherCode', null)
        ->assertSee('needs a RM 150.00 minimum')
        ->assertSee('RM 50.00 away');
});

test('COD is disabled with the reason when the total exceeds the cap', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(60000, flatFeeSen: 0); // RM 600.00 > RM 500.00 cap

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee('FREE')
        ->assertSee('COD unavailable: order exceeds RM 500.00');
});

test('COD is disabled with the reason when a selected item does not support it', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000, cod: false);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee("COD unavailable: some selected items don't support cash on delivery.");
});

test('placing a COD order creates it, empties the cart, and redirects to success', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSee('Cash on delivery')
        ->set('sellerNotes.'.$product->store_id, 'Leave at the guardhouse')
        ->set('paymentMethod', 'cod')
        ->call('placeOrder')
        ->assertDispatched('cart-updated', count: 0)
        ->assertRedirect(route('checkout.success', ['order' => Order::first()->order_no]));

    $order = Order::first();

    expect($order->payment_method)->toBe(PaymentMethod::Cod)
        ->and($order->subtotal_sen)->toBe(20000)
        ->and($order->grand_total_sen)->toBe(20500)
        ->and($buyer->cart->items()->count())->toBe(0);
});

test('an iPay88 order redirects to the payment bridge', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->call('placeOrder')
        ->assertRedirect(route('payments.ipay88.pay', Order::first()));

    expect(Order::first()->payment_method)->toBe(PaymentMethod::Ipay88);
});

test('a checkout failure surfaces as an error toast and persists no order', function () {
    [$buyer] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000, stock: 1);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $component = Livewire::actingAs($buyer)->test(Checkout::class);

    // The last unit sells out between page load and submit.
    $product->variants->first()->update(['stock' => 0]);

    $component
        ->call('placeOrder')
        ->assertDispatched('toast', type: 'error');

    expect(Order::count())->toBe(0)
        ->and($buyer->cart->items()->count())->toBe(1);
});

test('placing an order without an address shows the add-address prompt', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertSet('addressId', null)
        ->call('placeOrder')
        ->assertSee('Add a delivery address to place your order.');

    expect(Order::count())->toBe(0);
});

test('the success page belongs to the buyer — others get 403', function () {
    [$buyer, $address] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('checkout.success', ['order' => $order->order_no]))
        ->assertForbidden();

    $this->actingAs($buyer)
        ->get(route('checkout.success', ['order' => $order->order_no]))
        ->assertOk()
        ->assertSee('Order placed')
        ->assertSee($order->order_no)
        ->assertSee($product->store->name)
        ->assertSee('in cash when your order arrives');
});

test('the success page shows the pending-payment banner for iPay88 orders', function () {
    [$buyer, $address] = checkoutPageBuyer();
    $product = checkoutPageProduct(10000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);

    $this->actingAs($buyer)
        ->get(route('checkout.success', ['order' => $order->order_no]))
        ->assertOk()
        ->assertSee('Payment pending')
        ->assertSee('Complete your payment');
});
