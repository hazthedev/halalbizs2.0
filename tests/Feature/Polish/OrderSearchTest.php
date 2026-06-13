<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Orders;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use Livewire\Livewire;

function polishSearchSubOrder(User $buyer, string $productName): SubOrder
{
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Shipped)->create(['order_id' => $order->id]);

    $product = Product::factory()->create();

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $productName,
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    return $subOrder;
}

test('order search narrows the active tab by product name', function () {
    $buyer = User::factory()->create();
    $honey = polishSearchSubOrder($buyer, 'Honey Lemon Tea');
    $sambal = polishSearchSubOrder($buyer, 'Sambal Ikan Bilis');

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setTab', 'to-receive')
        ->assertSee('Honey Lemon Tea')
        ->assertSee('Sambal Ikan Bilis')
        ->set('search', 'honey lemon')
        ->assertSee('Honey Lemon Tea')
        ->assertDontSee('Sambal Ikan Bilis');
});

test('order search matches sub-order and parent order numbers', function () {
    $buyer = User::factory()->create();
    $honey = polishSearchSubOrder($buyer, 'Honey Lemon Tea');
    $sambal = polishSearchSubOrder($buyer, 'Sambal Ikan Bilis');

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setTab', 'to-receive')
        ->set('search', $sambal->sub_order_no)
        ->assertSee('Sambal Ikan Bilis')
        ->assertDontSee('Honey Lemon Tea')
        ->set('search', $honey->order->order_no)
        ->assertSee('Honey Lemon Tea')
        ->assertDontSee('Sambal Ikan Bilis');
});

test('a search with no hits shows the search empty state and can be cleared', function () {
    $buyer = User::factory()->create();
    polishSearchSubOrder($buyer, 'Honey Lemon Tea');

    Livewire::actingAs($buyer)
        ->test(Orders::class)
        ->call('setTab', 'to-receive')
        ->set('search', 'definitely-nothing-here')
        ->assertSee('No orders match')
        ->assertSee('Clear search')
        ->set('search', '')
        ->assertSee('Honey Lemon Tea');
});
