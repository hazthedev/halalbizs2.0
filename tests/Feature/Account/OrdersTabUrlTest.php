<?php

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('tab query param hydrates the orders page over HTTP', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Delivered)->create(['order_id' => $order->id]);

    $response = $this->actingAs($buyer)->get('/account/orders?tab=to-receive');

    $response->assertOk()
        ->assertSee($subOrder->store->name)
        ->assertSee('Order received');
});
