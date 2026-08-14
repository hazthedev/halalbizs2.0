<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\SubscriptionInterval;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Admin\Content\FlashSales;
use App\Livewire\Admin\Content\Vouchers;
use App\Livewire\Admin\Orders\Detail;
use App\Models\Address;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// The money MEDIUMs: M-6, M-14, M-15, M-25.

function moneyAdmin(array $permissions): User
{
    test()->seed(RoleSeeder::class);

    $admin = User::factory()->create(['two_factor_method' => 'email']); // EnsureAdmin requires 2FA
    $admin->assignRole('admin');
    $admin->syncPermissions($permissions);

    return $admin->fresh();
}

// ── M-6 · the admin refund that recorded no money ────────────────────────
test('marking a sub-order refunded goes through the money engine', function () {
    $admin = moneyAdmin(['orders.manage']);

    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'subtotal_sen' => 10000,
        'grand_total_sen' => 10000,
        'coin_redemption_sen' => 0,
        'discount_total_sen' => 0,
    ]);
    $store = Store::factory()->approved()->create();
    Product::factory()->create(['store_id' => $store->id]);

    $subOrder = SubOrder::factory()->status(SubOrderStatus::ReturnRequested)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'total_sen' => 10000,
        'commission_rate' => '5.00',
    ]);

    $payment = Payment::create([
        'order_id' => $order->id, 'gateway' => PaymentMethod::Ipay88, 'ref_no' => $order->order_no,
        'amount_sen' => 10000, 'currency' => 'MYR', 'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0, 'paid_at' => now(),
    ]);

    Livewire::actingAs($admin)->test(Detail::class, ['subOrder' => $subOrder])
        ->set('refundReference', 'IPAY88-PORTAL-99123')
        ->call('markRefunded');

    // The parallel implementation wrote NONE of these — it moved the status,
    // flipped the whole order, and put the reference in requery_result.
    expect($subOrder->fresh()->refunded_sen)->toBe(10000)
        ->and($payment->fresh()->refunded_sen)->toBe(10000)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('it will not report a multi-store order as fully refunded', function () {
    $admin = moneyAdmin(['orders.manage']);

    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88, 'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(), 'subtotal_sen' => 20000, 'grand_total_sen' => 20000,
        'coin_redemption_sen' => 0, 'discount_total_sen' => 0,
    ]);

    $subOrders = collect(['a', 'b'])->map(function () use ($order) {
        $store = Store::factory()->approved()->create();
        Product::factory()->create(['store_id' => $store->id]);

        return SubOrder::factory()->status(SubOrderStatus::ReturnRequested)->create([
            'order_id' => $order->id, 'store_id' => $store->id,
            'items_subtotal_sen' => 10000, 'shipping_fee_sen' => 0, 'shop_discount_sen' => 0,
            'total_sen' => 10000, 'commission_rate' => '5.00',
        ]);
    });

    Payment::create([
        'order_id' => $order->id, 'gateway' => PaymentMethod::Ipay88, 'ref_no' => $order->order_no,
        'amount_sen' => 20000, 'currency' => 'MYR', 'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0, 'paid_at' => now(),
    ]);

    Livewire::actingAs($admin)->test(Detail::class, ['subOrder' => $subOrders[0]])
        ->set('refundReference', 'IPAY88-PORTAL-99124')
        ->call('markRefunded');

    // The old code flipped the ORDER unconditionally, so refunding one store of
    // two reported the whole thing settled while the cumulative cap still
    // believed nothing had been refunded.
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($subOrders[1]->fresh()->status)->toBe(SubOrderStatus::ReturnRequested);
});

// ── M-14 · a typo shipped a free product ─────────────────────────────────
test('an unparseable flash-sale promo price is refused, not stored as zero', function () {
    $admin = moneyAdmin(['cms.manage']);
    $store = Store::factory()->approved()->create();
    $variant = Product::factory()->create(['store_id' => $store->id])->variants->first();

    $sale = FlashSale::create([
        'title' => 'Merdeka', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    Livewire::actingAs($admin)->test(FlashSales::class)
        ->set('addingToSaleId', $sale->id)
        ->set('itemVariantId', $variant->id)
        ->set('itemPromo', 'nineteen ninety')   // (int) null was 0 — a free product
        ->set('itemAllocated', 10)
        ->set('itemPerBuyer', 1)
        ->call('addItem')
        ->assertHasErrors(['itemPromo']);

    expect(FlashSaleItem::count())->toBe(0);
});

test('a real promo price still saves', function () {
    $admin = moneyAdmin(['cms.manage']);
    $store = Store::factory()->approved()->create();
    $variant = Product::factory()->create(['store_id' => $store->id])->variants->first();

    $sale = FlashSale::create([
        'title' => 'Merdeka', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);

    Livewire::actingAs($admin)->test(FlashSales::class)
        ->set('addingToSaleId', $sale->id)
        ->set('itemVariantId', $variant->id)
        ->set('itemPromo', '19.90')
        ->set('itemAllocated', 10)
        ->set('itemPerBuyer', 1)
        ->call('addItem')
        ->assertHasNoErrors();

    expect(FlashSaleItem::first()->promo_price_sen)->toBe(1990);
});

// ── M-15 · every click was another recurring COD order ───────────────────
test('subscribing twice to the same variant does not create a second subscription', function () {
    $user = User::factory()->create();
    $store = Store::factory()->approved()->create();
    $variant = Product::factory()->create(['store_id' => $store->id])->variants->first();
    $address = Address::factory()->create(['user_id' => $user->id]);

    $service = app(SubscriptionService::class);
    $first = $service->subscribe($user, $variant, $address, SubscriptionInterval::cases()[0]);
    $second = $service->subscribe($user, $variant, $address, SubscriptionInterval::cases()[0]);

    expect($second->id)->toBe($first->id)
        ->and(Subscription::count())->toBe(1);
});

// ── M-25 · the admin voucher screen listed every prize ever won ──────────
test('the platform voucher screen excludes spin-to-win prizes', function () {
    $admin = moneyAdmin(['vouchers.manage']);
    $buyer = User::factory()->create();

    // No VoucherFactory exists — the suite builds these inline.
    $make = fn (string $code, ?int $userId) => Voucher::create([
        'code' => $code,
        'scope' => VoucherScope::Platform,
        'user_id' => $userId,
        'type' => VoucherType::Percent,
        'percent' => 10,
        'value_sen' => 0,
        'min_spend_sen' => 0,
        'quota' => null,
        'per_user_limit' => 1,
        'used_count' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'is_active' => true,
    ]);

    $platform = $make('PLATFORM10', null);
    $prize = $make('SPINPRIZE7', $buyer->id);

    Livewire::actingAs($admin)->test(Vouchers::class)
        ->assertSee('PLATFORM10')
        ->assertDontSee('SPINPRIZE7');

    expect(Voucher::query()->whereKey($prize->id)->exists())->toBeTrue();
});
