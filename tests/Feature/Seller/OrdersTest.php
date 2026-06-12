<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Seller\Orders\Detail;
use App\Livewire\Seller\Orders\Index;
use App\Models\CancellationReason;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ordersSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/**
 * Direct Order/SubOrder/OrderItem fixture (status set at insert is test
 * setup — production transitions still go through the service).
 */
function ordersSubOrderFor(User $seller, SubOrderStatus $status = SubOrderStatus::Confirmed, array $attributes = []): SubOrder
{
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
        'subtotal_sen' => 10000,
        'shipping_total_sen' => 500,
        'discount_total_sen' => 0,
        'grand_total_sen' => 10500,
        'display_currency' => 'MYR',
        'display_rate' => 1,
        'placed_at' => now(),
    ]);

    $product = Product::factory()->create(['store_id' => $seller->store->id]);
    $variant = $product->variants->first();

    $subOrder = SubOrder::create([
        'sub_order_no' => SubOrder::generateSubOrderNo(),
        'order_id' => $order->id,
        'store_id' => $seller->store->id,
        'status' => $status,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 500,
        'shop_discount_sen' => 0,
        'total_sen' => 10500,
        'commission_rate' => 5.00,
        ...$attributes,
    ]);

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => $variant->options_label,
        'unit_price_sen' => 5000,
        'qty' => 2,
        'line_total_sen' => 10000,
    ]);

    return $subOrder;
}

test('orders index lists only the current store\'s sub-orders', function () {
    $seller = ordersSeller();
    $own = ordersSubOrderFor($seller);
    $foreign = ordersSubOrderFor(ordersSeller());

    $this->actingAs($seller)
        ->get(route('seller.orders.index'))
        ->assertOk()
        ->assertSee($own->sub_order_no)
        ->assertDontSee($foreign->sub_order_no);
});

test('tabs filter sub-orders by status', function () {
    $seller = ordersSeller();
    $confirmed = ordersSubOrderFor($seller, SubOrderStatus::Confirmed);
    $processing = ordersSubOrderFor($seller, SubOrderStatus::Processing);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->assertSet('tab', 'new')
        ->assertSee($confirmed->sub_order_no)
        ->assertDontSee($processing->sub_order_no)
        ->set('tab', 'to_ship')
        ->assertSee($processing->sub_order_no)
        ->assertDontSee($confirmed->sub_order_no);
});

test('confirm & pack transitions confirmed → processing and writes a history row', function () {
    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller, SubOrderStatus::Confirmed);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('confirmAndPack', $subOrder->id);

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Processing);

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->from_status)->toBe('confirmed')
        ->and($history->to_status)->toBe('processing')
        ->and($history->actor_type)->toBe(ActorType::Seller)
        ->and($history->actor_id)->toBe($seller->id);
});

test('confirm & pack cannot touch another store\'s sub-order', function () {
    $seller = ordersSeller();
    $foreign = ordersSubOrderFor(ordersSeller(), SubOrderStatus::Confirmed);

    expect(fn () => Livewire::actingAs($seller)->test(Index::class)->call('confirmAndPack', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreign->refresh()->status)->toBe(SubOrderStatus::Confirmed);
});

test('ship modal rejects a short tracking number and ships with a valid one', function () {
    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller, SubOrderStatus::Processing);

    $component = Livewire::actingAs($seller)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->call('openShipModal', $subOrder->id)
        ->assertSet('shippingSubOrderId', $subOrder->id)
        ->set('courier', 'J&T Express')
        ->set('trackingNo', 'JT12')
        ->call('ship')
        ->assertHasErrors(['trackingNo' => 'min']);

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Processing)
        ->and($subOrder->tracking_no)->toBeNull();

    $component
        ->set('trackingNo', 'JT123456789MY')
        ->call('ship')
        ->assertHasNoErrors()
        ->assertSet('shippingSubOrderId', null);

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Shipped)
        ->and($subOrder->tracking_courier)->toBe('J&T Express')
        ->and($subOrder->tracking_no)->toBe('JT123456789MY')
        ->and($subOrder->shipped_at)->not->toBeNull()
        ->and($subOrder->statusHistories()->get()->last()->to_status)->toBe('shipped');
});

test('ship modal "Other" courier stores the free-text courier name', function () {
    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller, SubOrderStatus::Processing);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->call('openShipModal', $subOrder->id)
        ->set('courier', 'other')
        ->set('courierOther', 'Skynet Express')
        ->set('trackingNo', 'SKY00012345')
        ->call('ship')
        ->assertHasNoErrors();

    $subOrder->refresh();
    expect($subOrder->tracking_courier)->toBe('Skynet Express')
        ->and($subOrder->status)->toBe(SubOrderStatus::Shipped);
});

test('mark delivered sets delivered_at and auto_complete_at', function () {
    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller, SubOrderStatus::Shipped, [
        'tracking_courier' => 'GDEX',
        'tracking_no' => 'GD123456',
        'shipped_at' => now()->subDay(),
    ]);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->call('markDelivered');

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Delivered)
        ->and($subOrder->delivered_at)->not->toBeNull()
        ->and($subOrder->auto_complete_at)->not->toBeNull()
        ->and($subOrder->auto_complete_at->isAfter($subOrder->delivered_at))->toBeTrue();
});

test('seller cancel requires a reason, restocks items and stores the reason', function () {
    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller, SubOrderStatus::Confirmed);

    $reason = CancellationReason::create(['label' => ['en' => 'Out of stock'], 'is_active' => true, 'position' => 1]);

    $variant = $subOrder->items->first()->variant;
    $variant->update(['stock' => 3]);

    $component = Livewire::actingAs($seller)->test(Detail::class, ['subOrder' => $subOrder]);

    // No reason selected → validation error, nothing changes.
    $component->call('cancelOrder')->assertHasErrors(['cancelReasonId' => 'required']);
    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Confirmed);

    $component
        ->set('cancelReasonId', $reason->id)
        ->call('cancelOrder')
        ->assertHasNoErrors();

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subOrder->cancel_reason)->toBe('Out of stock')
        ->and($subOrder->cancelled_at)->not->toBeNull()
        ->and($variant->refresh()->stock)->toBe(5); // 3 + qty 2 restocked

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('cancelled')
        ->and($history->actor_type)->toBe(ActorType::Seller);
});

test('detail returns 403 for another store\'s sub-order', function () {
    $seller = ordersSeller();
    $foreign = ordersSubOrderFor(ordersSeller());

    $this->actingAs($seller)
        ->get(route('seller.orders.show', $foreign))
        ->assertForbidden();
});

test('packing slip downloads for the owner store and 403s otherwise', function () {
    Storage::fake('local');

    $seller = ordersSeller();
    $subOrder = ordersSubOrderFor($seller);

    $this->actingAs($seller)
        ->get(route('seller.orders.packing-slip', $subOrder))
        ->assertOk()
        ->assertDownload("{$subOrder->sub_order_no}.pdf");

    $this->actingAs(ordersSeller())
        ->get(route('seller.orders.packing-slip', $subOrder))
        ->assertForbidden();
});
