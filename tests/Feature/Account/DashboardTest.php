<?php

use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Dashboard;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Wishlist;
use Livewire\Livewire;

function dashboardBuyer(): User
{
    return User::factory()->create();
}

/** A paid order for the buyer, paid in a given month-offset back from now. */
function dashboardPaidOrder(User $buyer, int $grandTotalSen, int $monthsAgo = 0): Order
{
    return Order::factory()->paid()->create([
        'user_id' => $buyer->id,
        'grand_total_sen' => $grandTotalSen,
        'paid_at' => now()->startOfMonth()->subMonths($monthsAgo)->addDays(2),
    ]);
}

/** A sub-order (with a couple of items) under an order for the buyer. */
function dashboardSubOrder(User $buyer, SubOrderStatus $status, ?Order $order = null, int $itemQty = 0): SubOrder
{
    $order ??= Order::factory()->create(['user_id' => $buyer->id]);

    $subOrder = SubOrder::factory()->status($status)->create(['order_id' => $order->id]);

    if ($itemQty > 0) {
        $product = Product::factory()->create();
        $subOrder->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'product_name' => $product->getTranslation('name', 'en'),
            'variant_label' => null,
            'unit_price_sen' => 2500,
            'qty' => $itemQty,
            'line_total_sen' => 2500 * $itemQty,
        ]);
    }

    return $subOrder;
}

test('guests are redirected to login from the dashboard', function () {
    $this->get(route('account.dashboard'))->assertRedirect(route('login'));
});

test('a buyer sees the dashboard with their own totals', function () {
    $buyer = dashboardBuyer();
    dashboardPaidOrder($buyer, 12000);

    $this->actingAs($buyer)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('Total spent');
});

test('the empty state shows when the buyer has no orders', function () {
    $buyer = dashboardBuyer();

    Livewire::actingAs($buyer)
        ->test(Dashboard::class)
        ->assertSee('Start shopping')
        ->assertSet('period', '6m');
});

test('stat totals are computed correctly', function () {
    $buyer = dashboardBuyer();

    // Two paid orders + one pending (pending excluded from spend, counted as placed).
    dashboardPaidOrder($buyer, 10000);
    dashboardPaidOrder($buyer, 5000);
    Order::factory()->create(['user_id' => $buyer->id, 'payment_status' => PaymentStatus::Pending]);

    // Items: one sub-order with qty 3 + another with qty 2 = 5.
    dashboardSubOrder($buyer, SubOrderStatus::Completed, itemQty: 3);
    dashboardSubOrder($buyer, SubOrderStatus::Shipped, itemQty: 2);

    // A review and two wishlist saves.
    $reviewSub = dashboardSubOrder($buyer, SubOrderStatus::Completed, itemQty: 1);
    $item = $reviewSub->items->first();
    Review::create([
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'store_id' => $reviewSub->store_id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'comment' => 'Great.',
    ]);
    Wishlist::create(['user_id' => $buyer->id, 'product_id' => Product::factory()->create()->id]);
    Wishlist::create(['user_id' => $buyer->id, 'product_id' => Product::factory()->create()->id]);

    Livewire::actingAs($buyer)
        ->test(Dashboard::class)
        ->assertViewHas('totalSpentSen', 15000)
        ->assertViewHas('ordersPlaced', 6) // 2 paid + 1 pending + 3 sub-order parents
        ->assertViewHas('itemsBought', 6)  // 3 + 2 + 1
        ->assertViewHas('reviewsWritten', 1)
        ->assertViewHas('wishlistSaved', 2);
});

test("another buyer's data is never counted", function () {
    $buyer = dashboardBuyer();
    $stranger = dashboardBuyer();

    dashboardPaidOrder($buyer, 8000);
    dashboardSubOrder($buyer, SubOrderStatus::Completed, itemQty: 2);
    Wishlist::create(['user_id' => $buyer->id, 'product_id' => Product::factory()->create()->id]);

    // Stranger's noise — must not leak into the buyer's numbers.
    dashboardPaidOrder($stranger, 99999);
    dashboardSubOrder($stranger, SubOrderStatus::Completed, itemQty: 9);
    Wishlist::create(['user_id' => $stranger->id, 'product_id' => Product::factory()->create()->id]);

    Livewire::actingAs($buyer)
        ->test(Dashboard::class)
        ->assertViewHas('totalSpentSen', 8000)
        ->assertViewHas('ordersPlaced', 2)
        ->assertViewHas('itemsBought', 2)
        ->assertViewHas('wishlistSaved', 1);
});

test('the donut status counts reflect only the buyer sub-orders', function () {
    $buyer = dashboardBuyer();

    dashboardSubOrder($buyer, SubOrderStatus::Completed);
    dashboardSubOrder($buyer, SubOrderStatus::Completed);
    dashboardSubOrder($buyer, SubOrderStatus::Shipped);
    dashboardSubOrder($buyer, SubOrderStatus::Cancelled);

    // Stranger noise.
    dashboardSubOrder(dashboardBuyer(), SubOrderStatus::Completed);

    $chart = Livewire::actingAs($buyer)
        ->test(Dashboard::class)
        ->viewData('statusChart');

    expect($chart['total'])->toBe(4)
        ->and(array_sum($chart['series']))->toBe(4)
        // Completed bucket = 2; it carries the emerald token.
        ->and($chart['series'])->toContain(2)
        ->and($chart['options']['colors'])->toContain('#047857');
});

test('changing the period recomputes the spend series and dispatches the chart', function () {
    $buyer = dashboardBuyer();

    // Spend in months: now, 4 months ago, 8 months ago.
    dashboardPaidOrder($buyer, 10000, monthsAgo: 0);
    dashboardPaidOrder($buyer, 20000, monthsAgo: 4);
    dashboardPaidOrder($buyer, 30000, monthsAgo: 8);

    $component = Livewire::actingAs($buyer)->test(Dashboard::class);

    // Default 6m → 6 monthly buckets; the 8-month-ago spend is outside the window.
    $sixMonth = $component->viewData('spendChart');
    expect($sixMonth['series'][0]['data'])->toHaveCount(6)
        ->and(array_sum($sixMonth['series'][0]['data']))->toBe(300.0); // (10000 + 20000) sen → RM

    // 3m → 3 buckets, only the most recent spend remains.
    $component->set('period', '3m')->assertDispatched('buyer-charts');
    $threeMonth = $component->viewData('spendChart');
    expect($threeMonth['series'][0]['data'])->toHaveCount(3)
        ->and(array_sum($threeMonth['series'][0]['data']))->toBe(100.0); // 10000 sen → RM

    // 12m → 12 buckets, all three months captured.
    $component->set('period', '12m');
    $twelveMonth = $component->viewData('spendChart');
    expect($twelveMonth['series'][0]['data'])->toHaveCount(12)
        ->and(array_sum($twelveMonth['series'][0]['data']))->toBe(600.0); // all three → RM
});
