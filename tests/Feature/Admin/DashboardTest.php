<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function dashboardAdmin(): User
{
    $user = User::factory()->create();
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
