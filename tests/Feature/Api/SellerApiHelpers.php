<?php

/**
 * Fixtures shared by the Seller*ApiTest files (required_once from each, so a
 * single file still runs on its own). Status set at insert is test setup —
 * production transitions still go through SubOrderStatusService.
 */

use App\Enums\ReturnStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;

/** A seller with a store in the given status (roles come from RoleSeeder in beforeEach). */
function sellerApiUser(StoreStatus $status = StoreStatus::Approved, bool $withRole = true): User
{
    $user = User::factory()->create();

    if ($withRole) {
        $user->assignRole('seller');
    }

    $store = Store::factory()->create(['user_id' => $user->id]);
    $store->forceFill(['status' => $status, 'approved_at' => $status === StoreStatus::Approved ? now() : null])->save();

    return $user->fresh();
}

/**
 * A fresh bearer request. One Pest test is one app instance, so the sanctum
 * guard would otherwise keep the first request's user for the next one.
 */
function sellerApiAs(User $user)
{
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('pest-phone')->plainTextToken);
}

/** A sub-order of the store with one snapshot item whose product is the store's. */
function sellerApiSubOrder(Store $store, SubOrderStatus $status = SubOrderStatus::Confirmed, array $attributes = [], array $orderAttributes = []): SubOrder
{
    $order = Order::factory()->create($orderAttributes);

    $subOrder = SubOrder::factory()->status($status)->create(array_merge([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 500,
        'shop_discount_sen' => 0,
        'total_sen' => 10500,
        'commission_rate' => '5.00',
    ], $attributes));

    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants->first();

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

function sellerApiReturn(SubOrder $subOrder, ReturnStatus $status = ReturnStatus::Requested): ReturnRequest
{
    $reason = ReturnReason::firstOrCreate(
        ['position' => 0],
        ['label' => ['en' => 'Item is damaged or defective'], 'is_active' => true],
    );

    return ReturnRequest::create([
        'sub_order_id' => $subOrder->id,
        'return_reason_id' => $reason->id,
        'description' => 'Arrived with a cracked lid.',
        'status' => $status,
        'respond_by' => now()->addHours(48),
    ]);
}
