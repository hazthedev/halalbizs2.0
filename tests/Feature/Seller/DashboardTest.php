<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Seller\Dashboard;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function dashboardSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/**
 * Direct Order/SubOrder/OrderItem fixture. Status is set at insert as test
 * setup only — production transitions still flow through the service
 * (CLAUDE.md hard rule 2). `createdAt`/`product` let tests place revenue in
 * specific buckets and rank specific products.
 */
function dashboardSubOrderFor(
    User $seller,
    SubOrderStatus $status = SubOrderStatus::Confirmed,
    int $totalSen = 10500,
    ?CarbonInterface $createdAt = null,
    ?Product $product = null,
    int $qty = 2,
): SubOrder {
    $buyer = User::factory()->create();

    $order = Order::create([
        'order_no' => Order::generateOrderNo(),
        'user_id' => $buyer->id,
        'payment_method' => PaymentMethod::Cod,
        'payment_status' => PaymentStatus::Pending,
        'shipping_address' => [
            'recipient_name' => 'Aisyah Binti Ali',
            'phone' => '+60123456789',
            'line1' => '12 Jalan Mawar 3/4',
            'line2' => null,
            'postcode' => '40000',
            'city' => 'Shah Alam',
            'state' => 'Selangor',
            'country' => 'MY',
        ],
        'subtotal_sen' => $totalSen,
        'shipping_total_sen' => 0,
        'discount_total_sen' => 0,
        'grand_total_sen' => $totalSen,
        'display_currency' => 'MYR',
        'display_rate' => 1,
        'placed_at' => $createdAt ?? now(),
    ]);

    $product ??= Product::factory()->create(['store_id' => $seller->store->id]);
    $variant = $product->variants->first();

    $subOrder = SubOrder::create([
        'sub_order_no' => SubOrder::generateSubOrderNo(),
        'order_id' => $order->id,
        'store_id' => $seller->store->id,
        'status' => $status,
        'items_subtotal_sen' => $totalSen,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'total_sen' => $totalSen,
        'commission_rate' => 5.00,
    ]);

    if ($createdAt !== null) {
        // created_at is not fillable; set it directly for bucketing tests.
        $subOrder->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => $variant->options_label,
        'unit_price_sen' => $qty > 0 ? intdiv($totalSen, $qty) : $totalSen,
        'qty' => $qty,
        'line_total_sen' => $totalSen,
    ]);

    return $subOrder;
}

test('approved seller sees the dashboard', function () {
    $seller = dashboardSeller();

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee(__('Revenue over time'))
        ->assertSee(__('Orders by status'))
        ->assertSee(__('Top products'));
});

test('revenue series reflects a seeded order for this store and is not all-zero', function () {
    $seller = dashboardSeller();
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 25000, now()->subDays(2));
    dashboardSubOrderFor($seller, SubOrderStatus::Confirmed, 18000, now()->subDay());

    $data = Livewire::actingAs($seller)
        ->test(Dashboard::class)
        ->instance()
        ->revenueData();

    // Whole-ringgit ints: 250 + 180 = 430.
    expect(array_sum($data))->toBeGreaterThan(0)
        ->and(array_sum($data))->toBe(430);
});

test('pending and cancelled orders are excluded from revenue', function () {
    $seller = dashboardSeller();
    dashboardSubOrderFor($seller, SubOrderStatus::PendingPayment, 99999, now()->subDay());
    dashboardSubOrderFor($seller, SubOrderStatus::Cancelled, 88888, now()->subDay());

    $data = Livewire::actingAs($seller)
        ->test(Dashboard::class)
        ->instance()
        ->revenueData();

    expect(array_sum($data))->toBe(0);
});

test('another store\'s orders are excluded from the revenue series (no leakage)', function () {
    $seller = dashboardSeller();
    $other = dashboardSeller();

    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 10000, now()->subDay());
    dashboardSubOrderFor($other, SubOrderStatus::Completed, 50000, now()->subDay());

    $data = Livewire::actingAs($seller)
        ->test(Dashboard::class)
        ->instance()
        ->revenueData();

    // Only this store's RM100 — the other store's RM500 must not appear.
    expect(array_sum($data))->toBe(100);
});

test('changing the period recomputes the revenue buckets', function () {
    $seller = dashboardSeller();
    // Inside 7d but everywhere; and one 45 days ago (outside 7d/30d, inside 90d).
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 10000, now()->subDays(3));
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 20000, now()->subDays(45));

    $component = Livewire::actingAs($seller)->test(Dashboard::class);

    $component->set('period', '7d');
    $sevenDay = $component->instance()->revenueData();

    $component->set('period', '90d');
    $ninetyDay = $component->instance()->revenueData();

    // Bucket counts differ with the period.
    expect(count($sevenDay))->toBe(7)
        ->and(count($ninetyDay))->toBe(90);

    // 7d sees only the recent RM100; 90d sees both (RM100 + RM200).
    expect(array_sum($sevenDay))->toBe(100)
        ->and(array_sum($ninetyDay))->toBe(300);
});

test('status donut counts this store\'s sub-orders by status', function () {
    $seller = dashboardSeller();
    dashboardSubOrderFor($seller, SubOrderStatus::Confirmed, 10000, now()->subDay());
    dashboardSubOrderFor($seller, SubOrderStatus::Confirmed, 10000, now()->subDay());
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 10000, now()->subDay());

    // Another store's order must not pollute the donut.
    dashboardSubOrderFor(dashboardSeller(), SubOrderStatus::Completed, 99999, now()->subDay());

    $counts = Livewire::actingAs($seller)
        ->test(Dashboard::class)
        ->instance()
        ->statusCounts();

    expect($counts[SubOrderStatus::Confirmed->label()])->toBe(2)
        ->and($counts[SubOrderStatus::Completed->label()])->toBe(1)
        ->and(array_sum($counts))->toBe(3);
});

test('top products lists this store\'s products ranked by units sold', function () {
    $seller = dashboardSeller();

    $hot = Product::factory()->create(['store_id' => $seller->store->id, 'name' => ['en' => 'Hot Seller', 'ms' => 'Hot Seller']]);
    $cold = Product::factory()->create(['store_id' => $seller->store->id, 'name' => ['en' => 'Cold Item', 'ms' => 'Cold Item']]);

    // Hot: qty 5 in period. Cold: qty 1.
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 10000, now()->subDay(), $hot, 5);
    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 10000, now()->subDay(), $cold, 1);

    // Foreign store product should never appear.
    $foreignSeller = dashboardSeller();
    $foreign = Product::factory()->create(['store_id' => $foreignSeller->store->id, 'name' => ['en' => 'Foreign Goods', 'ms' => 'Foreign Goods']]);
    dashboardSubOrderFor($foreignSeller, SubOrderStatus::Completed, 99999, now()->subDay(), $foreign, 99);

    $names = Livewire::actingAs($seller)
        ->test(Dashboard::class)
        ->instance()
        ->topProductNames();

    expect($names)->toContain('Hot Seller')
        ->and($names)->toContain('Cold Item')
        ->and($names)->not->toContain('Foreign Goods')
        ->and($names[0])->toBe('Hot Seller'); // ranked first by units
});

test('earnings strip reflects available balance, gross and commission for this store', function () {
    $seller = dashboardSeller();
    $store = $seller->store;

    dashboardSubOrderFor($seller, SubOrderStatus::Completed, 30000, now()->subDay());

    // Ledger: a sale credit (available) and a commission debit (negative).
    StoreLedgerEntry::create([
        'store_id' => $store->id,
        'type' => LedgerEntryType::Sale,
        'amount_sen' => 30000,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Sale',
    ]);
    StoreLedgerEntry::create([
        'store_id' => $store->id,
        'type' => LedgerEntryType::Commission,
        'amount_sen' => -1500,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Commission',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee(Money::format(28500))   // available balance: 30000 - 1500
        ->assertSee(Money::format(30000))   // gross this period
        ->assertSee(Money::format(1500));   // commission charged (absolute)
});

test('dashboard keeps the stat cards, to-do strip and recent orders', function () {
    $seller = dashboardSeller();
    $subOrder = dashboardSubOrderFor($seller, SubOrderStatus::Confirmed, 12345, now());

    $this->actingAs($seller)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertSee(__("Today's orders"))
        ->assertSee(__('To ship'))
        ->assertSee(__('Live products'))
        ->assertSee(__('Recent orders'))
        ->assertSee($subOrder->sub_order_no);
});
