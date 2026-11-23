<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\ReturnService;
use App\Services\SubOrderStatusService;
use App\Settings\OrderSettings;
use Database\Seeders\RoleSeeder;

// HB-DEC-1123d (Haze 2026-11-23): a delivered sub-order keeps the return
// window it had on delivery day; a later settings change applies only to
// orders delivered after it.

beforeEach(fn () => $this->seed(RoleSeeder::class));

function windowSetting(int $days): void
{
    $settings = app(OrderSettings::class);
    $settings->return_window_days = $days;
    $settings->save();
}

/** A shipped sub-order moved to delivered through the one status path. */
function windowDelivered(): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $subOrder = SubOrder::factory()->status(SubOrderStatus::Shipped)->create([
        'order_id' => Order::factory()->create(['user_id' => $buyer->id, 'payment_method' => PaymentMethod::Ipay88])->id,
        'store_id' => Store::factory()->approved()->create(['commission_rate' => '5.00'])->id,
        'items_subtotal_sen' => 20000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'tax_sen' => 0,
        'total_sen' => 20000,
        'commission_rate' => '5.00',
    ]);

    return app(SubOrderStatusService::class)->transition($subOrder, SubOrderStatus::Delivered, ActorType::System);
}

test('a longer window set after delivery does not reopen returns: delivered on 7, setting 30, day 10 refused', function () {
    $this->freezeTime();
    windowSetting(7);
    $subOrder = windowDelivered();

    windowSetting(30);
    $this->travel(10)->days();

    expect(app(ReturnService::class)->canRequest($subOrder->fresh()))->toBeFalse();
});

test('a shorter window set after delivery does not cut promised days: delivered on 7, setting 3, day 5 allowed', function () {
    $this->freezeTime();
    windowSetting(7);
    $subOrder = windowDelivered();

    windowSetting(3);
    $this->travel(5)->days();

    expect(app(ReturnService::class)->canRequest($subOrder->fresh()))->toBeTrue();
});

test('the seller hold ends when the delivery-day window closes, not the current setting', function () {
    $this->freezeTime();
    windowSetting(7);
    $subOrder = windowDelivered();
    $deliveredAt = $subOrder->delivered_at->copy();

    windowSetting(30);
    app(LedgerService::class)->recordCompletion($subOrder->fresh());

    $sale = $subOrder->ledgerEntries()->where('type', 'sale')->first();
    expect($sale->status)->toBe(LedgerEntryStatus::Pending)
        ->and($sale->available_at->equalTo($deliveredAt->copy()->addDays(7)))->toBeTrue();
});

test('a sub-order delivered before the snapshot existed (NULL) follows the current setting, as before', function () {
    $this->freezeTime();
    windowSetting(7);
    $subOrder = windowDelivered();
    $subOrder->forceFill(['return_closes_at' => null])->save();

    windowSetting(30);
    $this->travel(10)->days();

    expect(app(ReturnService::class)->canRequest($subOrder->fresh()))->toBeTrue()
        ->and(app(LedgerService::class)->releaseAt($subOrder->fresh())->equalTo($subOrder->delivered_at->copy()->addDays(30)))->toBeTrue();
});
