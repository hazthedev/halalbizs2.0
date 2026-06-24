<?php

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['subscriptions.enabled' => true, 'subscriptions.discount_bp' => 500]); // 5%
});

function subBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function subProduct(int $priceSen = 10_000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['shipping_mode' => 'flat', 'shipping_flat_fee_sen' => 0, 'free_shipping_over_sen' => null]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 50]);

    return $product;
}

test('subscribing creates an active schedule with the standing discount', function () {
    [$buyer, $address] = subBuyer();
    $product = subProduct();

    $sub = app(SubscriptionService::class)->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Monthly, 2);

    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->qty)->toBe(2)
        ->and($sub->discount_bp)->toBe(500)
        ->and($sub->payment_method)->toBe(PaymentMethod::Cod)
        ->and($sub->next_run_at->isFuture())->toBeTrue();
});

test('processing a due subscription places a discounted COD order and advances the schedule', function () {
    [$buyer, $address] = subBuyer();
    $product = subProduct(10_000);
    $svc = app(SubscriptionService::class);

    $sub = $svc->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Weekly, 1);
    $sub->update(['next_run_at' => now()->subMinute()]);

    expect($svc->processDue())->toBe(1);

    $order = Order::where('subscription_id', $sub->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe(PaymentMethod::Cod)
        ->and($order->subOrders->first()->items->first()->unit_price_sen)->toBe(9500) // 5% off RM100
        ->and($order->grand_total_sen)->toBe(9500);

    $sub->refresh();
    expect($sub->next_run_at->isFuture())->toBeTrue()
        ->and($sub->last_ordered_at)->not->toBeNull();

    // Idempotent — no longer due, so a second pass places nothing.
    expect($svc->processDue())->toBe(0);
});

test('an explicit-lines subscription order never touches the buyer cart', function () {
    [$buyer, $address] = subBuyer();
    $product = subProduct();

    app(CartService::class)->addItem($buyer, $product->variants->first(), 3);
    expect($buyer->cart->items()->count())->toBe(1);

    $svc = app(SubscriptionService::class);
    $sub = $svc->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Weekly);
    $sub->update(['next_run_at' => now()->subMinute()]);
    $svc->processDue();

    // The cart still holds the buyer's own selected line — checkout via explicit
    // lines must neither read nor clear it.
    expect($buyer->cart->fresh()->items()->count())->toBe(1);
});

test('a paused subscription is not processed', function () {
    [$buyer, $address] = subBuyer();
    $product = subProduct();
    $svc = app(SubscriptionService::class);

    $sub = $svc->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Weekly);
    $svc->pause($sub);
    $sub->update(['next_run_at' => now()->subMinute()]);

    expect($svc->processDue())->toBe(0)
        ->and(Order::where('subscription_id', $sub->id)->exists())->toBeFalse();
});

test('pause, resume and cancel manage the schedule', function () {
    [$buyer, $address] = subBuyer();
    $product = subProduct();
    $svc = app(SubscriptionService::class);
    $sub = $svc->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Monthly);

    $svc->pause($sub);
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Paused);

    $svc->resume($sub);
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Active);

    $svc->cancel($sub);
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($sub->fresh()->next_run_at)->toBeNull();
});
