<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function dashboardAdmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

test('guests are redirected and non-admins get 403 on the dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
});

test('the dashboard renders the stat labels and queue cards', function () {
    Store::factory()->count(2)->create(); // pending applications

    $this->actingAs(dashboardAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('GMV (paid)')
        ->assertSee('Commission revenue')
        ->assertSee('Boost revenue')
        ->assertSee('Orders today')
        ->assertSee('New buyers today')
        ->assertSee('Pending queues')
        ->assertSee('Seller applications')
        ->assertSee('Products pending review')
        ->assertSee('Payout requests')
        ->assertSee('Return escalations')
        ->assertSee('GMV — last 30 days')
        ->assertSee('Orders by status')
        ->assertSee('Top stores by GMV');
});

test('the dashboard sums paid GMV and counts pending applications', function () {
    Store::factory()->count(3)->create(); // pending queue → 3
    Order::factory()->paid()->create(['grand_total_sen' => 12345]);
    Order::factory()->create(['grand_total_sen' => 99999]); // unpaid COD — excluded from GMV

    Livewire::actingAs(dashboardAdmin())
        ->test(Dashboard::class)
        ->assertSee('RM 123.45')
        ->assertDontSee('RM 999.99');
});

test('commission revenue and top stores come from completed sub-orders', function () {
    $store = Store::factory()->approved()->create(['name' => 'Kedai Juara']);

    SubOrder::factory()
        ->status(SubOrderStatus::Completed)
        ->create([
            'store_id' => $store->id,
            'total_sen' => 25000,
            'commission_sen' => 1250,
        ]);

    Livewire::actingAs(dashboardAdmin())
        ->test(Dashboard::class)
        ->assertSee('RM 12.50')
        ->assertSee('Kedai Juara')
        ->assertSee('RM 250.00');
});

test('the period picker only accepts known periods', function () {
    Livewire::actingAs(dashboardAdmin())
        ->test(Dashboard::class)
        ->call('setPeriod', 'today')
        ->assertSet('period', 'today')
        ->call('setPeriod', 'all-time')
        ->assertSet('period', 'today');
});

test('the GMV chart dataset reflects seeded paid orders and is not static', function () {
    Order::factory()->paid()->create(['grand_total_sen' => 12345, 'paid_at' => now()]);
    Order::factory()->paid()->create(['grand_total_sen' => 7000, 'paid_at' => now()]);
    Order::factory()->create(['grand_total_sen' => 99999]); // unpaid — excluded

    $component = Livewire::actingAs(dashboardAdmin())->test(Dashboard::class);

    $payload = invadeChart($component, 'gmvChartPayload');

    expect($payload['type'])->toBe('area');
    // Series carries ringgit (sen ÷ 100) for the RM-formatted axis: 12345+7000 sen → RM 193.
    expect(array_sum($payload['series'][0]['data']))->toBe(193);
});

test('the status donut counts all sub-orders per status', function () {
    SubOrder::factory()->count(2)->status(SubOrderStatus::Completed)->create();
    SubOrder::factory()->status(SubOrderStatus::Cancelled)->create();
    SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create();

    $payload = invadeChart(
        Livewire::actingAs(dashboardAdmin())->test(Dashboard::class),
        'statusChartPayload'
    );

    expect($payload['type'])->toBe('donut');
    expect(array_sum($payload['series']))->toBe(4); // total slices
    // Completed slice = 2 and carries the emerald (money) colour.
    $completedIndex = array_search(SubOrderStatus::Completed->label(), $payload['labels'], true);
    expect($payload['series'][$completedIndex])->toBe(2);
    expect($payload['options']['colors'][$completedIndex])->toBe('#047857');
});

test('top categories are computed from completed sub-orders', function () {
    $winner = Category::factory()->create(['name' => ['en' => 'Electronics', 'ms' => 'Elektronik']]);
    $loser = Category::factory()->create(['name' => ['en' => 'Books', 'ms' => 'Buku']]);

    $winnerProduct = Product::factory()->create(['category_id' => $winner->id]);
    $loserProduct = Product::factory()->create(['category_id' => $loser->id]);

    $completed = SubOrder::factory()->status(SubOrderStatus::Completed)->create();
    $pending = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create();

    $completed->items()->create([
        'product_id' => $winnerProduct->id, 'product_name' => 'Phone',
        'qty' => 1, 'unit_price_sen' => 30000, 'line_total_sen' => 30000,
    ]);
    $completed->items()->create([
        'product_id' => $loserProduct->id, 'product_name' => 'Novel',
        'qty' => 1, 'unit_price_sen' => 5000, 'line_total_sen' => 5000,
    ]);
    // Items on a non-completed sub-order must NOT count.
    $pending->items()->create([
        'product_id' => $loserProduct->id, 'product_name' => 'Novel',
        'qty' => 1, 'unit_price_sen' => 99999, 'line_total_sen' => 99999,
    ]);

    $payload = invadeChart(
        Livewire::actingAs(dashboardAdmin())->test(Dashboard::class),
        'categoriesChartPayload'
    );

    expect($payload['type'])->toBe('bar');
    expect($payload['labels'][0])->toBe('Electronics'); // ranked first by GMV
    expect($payload['series'][0]['data'][0])->toBe(300); // 30000 sen → RM 300, completed only
});

test('boost revenue sums ProductBoost amount_sen in the period', function () {
    $product = Product::factory()->create();

    ProductBoost::create([
        'product_id' => $product->id, 'store_id' => $product->store_id,
        'starts_at' => now(), 'ends_at' => now()->addDays(7),
        'amount_sen' => 1500, 'status' => 'active',
    ]);
    ProductBoost::create([
        'product_id' => $product->id, 'store_id' => $product->store_id,
        'starts_at' => now(), 'ends_at' => now()->addDays(7),
        'amount_sen' => 2500, 'status' => 'active',
    ]);

    Livewire::actingAs(dashboardAdmin())
        ->test(Dashboard::class)
        ->assertSee('Boost revenue')
        ->assertSee('RM 40.00'); // 1500 + 2500 sen
});

test('changing the period recomputes the GMV dataset', function () {
    // A paid order 20 days ago is in 30d but not today/7d.
    Order::factory()->paid()->create(['grand_total_sen' => 50000, 'paid_at' => now()->subDays(20)]);

    $component = Livewire::actingAs(dashboardAdmin())->test(Dashboard::class);

    $thirty = array_sum(invadeChart($component, 'gmvChartPayload')['series'][0]['data']);

    $component->call('setPeriod', 'today');
    $today = array_sum(invadeChart($component, 'gmvChartPayload')['series'][0]['data']);

    expect($thirty)->toBe(500); // 50000 sen → RM 500
    expect($today)->toBe(0);
    expect($thirty)->not->toBe($today);
});

/**
 * Reach a private chart-payload builder on the live component instance so the
 * test can assert on the actual ApexCharts payload (not just rendered HTML).
 */
function invadeChart(Testable $component, string $method): array
{
    $instance = $component->instance();
    $ref = new ReflectionMethod($instance, $method);

    return $ref->invoke($instance);
}
