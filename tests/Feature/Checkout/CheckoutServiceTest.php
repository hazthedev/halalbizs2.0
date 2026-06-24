<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SubOrderStatusNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function checkoutBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function cartWith(User $buyer, Product $product, int $qty = 1): void
{
    app(CartService::class)->addItem($buyer, $product->variants->first(), $qty);
}

test('COD checkout splits orders per store with snapshots and stock decrement', function () {
    [$buyer, $address] = checkoutBuyer();

    $productA = Product::factory()->create(['cod_enabled' => true]);
    $productB = Product::factory()->create(['cod_enabled' => true]); // different store by default
    $productA->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 5]);
    $productB->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 3]);

    cartWith($buyer, $productA, 2);
    cartWith($buyer, $productB, 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->subOrders)->toHaveCount(2)
        ->and($order->subtotal_sen)->toBe(25000)
        ->and($order->grand_total_sen)->toBe(25000 + $order->shipping_total_sen)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->shipping_address['state'])->toBe('Selangor');

    $subOrder = $order->subOrders->firstWhere('store_id', $productA->store_id);
    expect($subOrder->status)->toBe(SubOrderStatus::Confirmed)
        ->and($subOrder->commission_rate)->not->toBeNull()
        ->and($subOrder->statusHistories)->toHaveCount(1);

    $item = $subOrder->items->first();
    expect($item->product_name)->toBe($productA->getTranslation('name', 'en'))
        ->and($item->unit_price_sen)->toBe(10000)
        ->and($item->line_total_sen)->toBe(20000);

    expect($productA->variants->first()->fresh()->stock)->toBe(3)
        ->and($buyer->cart->items)->toHaveCount(0)
        ->and($order->payment->amount_sen)->toBe($order->grand_total_sen);
});

test('SST is computed, snapshotted and added to totals for a registered seller', function () {
    [$buyer, $address] = checkoutBuyer(); // Selangor, Malaysia

    $product = Product::factory()->create(['cod_enabled' => true, 'tax_class' => TaxClass::Standard]);
    $product->store->update([
        'sst_registered' => true,
        'sst_number' => 'W10-1234-56789',
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 0,
        'free_shipping_over_sen' => null,
    ]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 5]);
    cartWith($buyer, $product, 2); // RM200.00 of goods

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $subOrder = $order->subOrders->first();
    $item = $subOrder->items->first();

    expect($item->tax_rate_bp)->toBe(1000)            // 10% standard rate snapshotted
        ->and($item->tax_sen)->toBe(2000)             // 10% of RM200.00
        ->and($subOrder->tax_sen)->toBe(2000)
        ->and($order->tax_total_sen)->toBe(2000)
        ->and($subOrder->total_sen)->toBe(20000 + $subOrder->shipping_fee_sen + 2000)
        ->and($order->grand_total_sen)->toBe(20000 + $order->shipping_total_sen + 2000)
        ->and($order->payment->amount_sen)->toBe($order->grand_total_sen);
});

test('no SST is charged when the seller is not tax-registered', function () {
    [$buyer, $address] = checkoutBuyer();

    // Store defaults to sst_registered = false even though the product is taxable.
    $product = Product::factory()->create(['cod_enabled' => true, 'tax_class' => TaxClass::Standard]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 5]);
    cartWith($buyer, $product, 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($order->tax_total_sen)->toBe(0)
        ->and($order->subOrders->first()->tax_sen)->toBe(0)
        ->and($order->subOrders->first()->items->first()->tax_sen)->toBe(0)
        ->and($order->grand_total_sen)->toBe($order->subtotal_sen + $order->shipping_total_sen);
});

test('iPay88 checkout starts pending_payment with an expiry window', function () {
    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 5]); // factory stock can roll 0
    cartWith($buyer, $product);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);

    expect($order->subOrders->first()->status)->toBe(SubOrderStatus::PendingPayment)
        ->and($order->expires_at)->not->toBeNull()
        ->and(now()->diffInMinutes($order->expires_at))->toBeGreaterThan(55);
});

test('checkout fails atomically when stock is short — nothing persists', function () {
    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 1, 'sale_price_sen' => null]);

    cartWith($buyer, $product, 1);
    // Someone else takes the last unit after the item entered the cart.
    $product->variants->first()->update(['stock' => 0]);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class);

    expect(Order::count())->toBe(0)
        ->and(SubOrder::count())->toBe(0)
        ->and($buyer->cart->items()->count())->toBe(1);
});

test('last-stock race: second checkout of the same variant fails, exactly one succeeds', function () {
    [$buyerA, $addressA] = checkoutBuyer();
    [$buyerB, $addressB] = checkoutBuyer();

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 1, 'price_sen' => 1000, 'sale_price_sen' => null]);

    cartWith($buyerA, $product, 1);
    cartWith($buyerB, $product, 1);

    $orderA = app(CheckoutService::class)->place($buyerA, $addressA, PaymentMethod::Cod);

    expect(fn () => app(CheckoutService::class)->place($buyerB, $addressB, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class);

    expect(Order::count())->toBe(1)
        ->and($product->variants->first()->fresh()->stock)->toBe(0)
        ->and($orderA->subOrders->first()->items->first()->qty)->toBe(1);
});

test('quota-1 voucher: second redemption fails, used_count stays at 1', function () {
    [$buyerA, $addressA] = checkoutBuyer();
    [$buyerB, $addressB] = checkoutBuyer();

    $productA = Product::factory()->create(['cod_enabled' => true]);
    $productB = Product::factory()->for($productA->store)->create(['cod_enabled' => true]);
    $productA->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);
    $productB->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);

    $voucher = Voucher::create([
        'scope' => VoucherScope::Platform,
        'code' => 'ONCE',
        'type' => VoucherType::Fixed,
        'value_sen' => 500,
        'quota' => 1,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);

    cartWith($buyerA, $productA);
    cartWith($buyerB, $productB);

    $orderA = app(CheckoutService::class)->place($buyerA, $addressA, PaymentMethod::Cod, 'ONCE');

    expect($orderA->discount_total_sen)->toBe(500)
        ->and($voucher->fresh()->used_count)->toBe(1)
        ->and($voucher->usages()->count())->toBe(1);

    expect(fn () => app(CheckoutService::class)->place($buyerB, $addressB, PaymentMethod::Cod, 'ONCE'))
        ->toThrow(CheckoutException::class);

    expect($voucher->fresh()->used_count)->toBe(1)
        ->and(Order::count())->toBe(1);
});

test('COD cap and disabled products are rejected', function () {
    [$buyer, $address] = checkoutBuyer();

    $expensive = Product::factory()->create(['cod_enabled' => true]);
    $expensive->variants->first()->update(['price_sen' => 60000, 'sale_price_sen' => null, 'stock' => 5]); // RM600 > RM500 cap
    cartWith($buyer, $expensive);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'Cash on delivery is limited');

    $buyer->cart->items()->delete();

    $noCod = Product::factory()->create(['cod_enabled' => false]);
    $noCod->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 5]);
    cartWith($buyer, $noCod);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'cash on delivery');
});

test('holiday-mode store blocks checkout', function () {
    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['holiday_mode' => true]);
    cartWith($buyer, $product);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'not accepting orders');
});

test('shipping uses the state matrix and free-shipping threshold', function () {
    [$buyer, $address] = checkoutBuyer(); // Selangor

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update([
        'shipping_mode' => 'matrix',
        'shipping_matrix' => ['Selangor' => 700, 'Sabah' => 1500],
        'shipping_flat_fee_sen' => 500,
    ]);
    $product->variants->first()->update(['price_sen' => 2000, 'sale_price_sen' => null, 'stock' => 10]);
    cartWith($buyer, $product);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    expect($order->shipping_total_sen)->toBe(700);

    // Free over threshold zeroes the fee.
    $product->store->update(['free_shipping_over_sen' => 1500]);
    cartWith($buyer, $product, 1);
    $order2 = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    expect($order2->shipping_total_sen)->toBe(0);
});

test('cancel restocks items and COD delivery settles payment', function () {
    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 5]);
    cartWith($buyer, $product, 2);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $subOrder = $order->subOrders->first();
    expect($variant->fresh()->stock)->toBe(3);

    // Cancel pre-ship → restock.
    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $buyer->id, 'Changed my mind');
    expect($variant->fresh()->stock)->toBe(5)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subOrder->fresh()->cancel_reason)->toBe('Changed my mind');

    // Fresh order: walk to delivered → COD payment settles.
    cartWith($buyer, $product, 1);
    $order2 = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $so2 = $order2->subOrders->first();

    $statusService = app(SubOrderStatusService::class);
    $statusService->transition($so2, SubOrderStatus::Processing, ActorType::Seller);
    $statusService->transition($so2->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($so2->fresh(), ActorType::System);

    expect($order2->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order2->fresh()->paid_at)->not->toBeNull()
        ->and($so2->fresh()->auto_complete_at)->not->toBeNull();
});

test('status notifications reach buyer and seller on confirmation', function () {
    Notification::fake();

    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 5]); // factory stock can roll 0
    cartWith($buyer, $product);

    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    Notification::assertSentTo($buyer, SubOrderStatusNotification::class);
    Notification::assertSentTo($product->store->user, SubOrderStatusNotification::class);
});

test('invalid status transitions are rejected', function () {
    [$buyer, $address] = checkoutBuyer();
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 5]); // factory stock can roll 0
    cartWith($buyer, $product);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
    $subOrder = $order->subOrders->first();

    expect(fn () => app(SubOrderStatusService::class)
        ->transition($subOrder, SubOrderStatus::Delivered, ActorType::Seller))
        ->toThrow(InvalidArgumentException::class);
});
