<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Orders;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\GeneralSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// HB-BUY1: purchasing_enabled false hides every purchase control; true leaves them.

beforeEach(fn () => $this->seed(RoleSeeder::class));

function buyingSwitch(bool $on): void
{
    $s = app(GeneralSettings::class);
    $s->purchasing_enabled = $on;
    $s->save();
}

test('product page: no Add to cart / Buy now while off, both present while on', function () {
    $product = Product::factory()->create();
    $product->variants->first()->update(['stock' => 5]);

    buyingSwitch(false);
    $this->get(route('product.show', $product->slug))->assertOk()
        ->assertDontSee('Add to cart')->assertDontSee('Buy now');

    buyingSwitch(true);
    $this->get(route('product.show', $product->slug))->assertOk()
        ->assertSee('Add to cart')->assertSee('Buy now');
});

test('orders list: Buy again hidden while off, shown while on', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);

    buyingSwitch(false);
    Livewire::actingAs($buyer)->test(Orders::class)->call('setTab', 'completed')->assertDontSee('Buy again');

    buyingSwitch(true);
    Livewire::actingAs($buyer)->test(Orders::class)->call('setTab', 'completed')->assertSee('Buy again');
});
