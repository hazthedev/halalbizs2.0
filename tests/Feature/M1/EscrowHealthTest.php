<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('held escrow balance reflects in-flight paid orders net of commission', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['commission_rate' => 5.00, 'shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
    $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();
    app(SubOrderStatusService::class)->transition($order->subOrders->first(), SubOrderStatus::Confirmed, ActorType::System);

    // total 10000 − commission (5% of 10000 = 500) = 9500 held.
    expect($product->store->fresh()->heldBalanceSen())->toBe(9500);
});

test('abandoned-cart reminders go out once per idle cart and skip fresh ones', function () {
    Notification::fake();

    $idle = User::factory()->create();
    $idle->assignRole('buyer');
    $fresh = User::factory()->create();
    $fresh->assignRole('buyer');

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['stock' => 10, 'sale_price_sen' => null]);

    app(CartService::class)->addItem($idle, $product->variants->first(), 1);
    app(CartService::class)->addItem($fresh, $product->variants->first(), 1);

    // Age the idle cart past the 4h threshold (bypass model timestamps).
    Cart::where('user_id', $idle->id)->update(['updated_at' => now()->subHours(5)]);

    $this->artisan('carts:remind-abandoned')->assertSuccessful();

    Notification::assertSentTo($idle, AbandonedCartNotification::class);
    Notification::assertNotSentTo($fresh, AbandonedCartNotification::class);
    expect($idle->cart->fresh()->reminded_at)->not->toBeNull();

    // A second run inside the cooldown does not re-notify.
    Notification::fake();
    $this->artisan('carts:remind-abandoned')->assertSuccessful();
    Notification::assertNotSentTo($idle, AbandonedCartNotification::class);
});

test('seller health computes cancel, return and defect rates in basis points', function () {
    $store = Store::factory()->create();

    SubOrder::factory()->count(8)->status(SubOrderStatus::Completed)->create(['store_id' => $store->id]);
    SubOrder::factory()->status(SubOrderStatus::Cancelled)->create(['store_id' => $store->id]);
    SubOrder::factory()->status(SubOrderStatus::Refunded)->create(['store_id' => $store->id]);

    $this->artisan('seller:compute-health')->assertSuccessful();

    $health = $store->health()->first();

    expect($health)->not->toBeNull()
        ->orders_counted->toBe(10)
        ->cancel_rate_bp->toBe(1000)   // 1/10
        ->return_rate_bp->toBe(1000)   // 1/10 (refunded)
        ->defect_rate_bp->toBe(2000);  // 2/10
});
