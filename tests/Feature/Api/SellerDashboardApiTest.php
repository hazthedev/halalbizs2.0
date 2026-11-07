<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Models\Product;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('GET seller/dashboard returns store-scoped counts, balances and recent orders', function () {
    $seller = sellerApiUser();
    $store = $seller->store;

    $confirmed = sellerApiSubOrder($store, SubOrderStatus::Confirmed);
    sellerApiSubOrder($store, SubOrderStatus::Shipped);
    sellerApiSubOrder($store, SubOrderStatus::Cancelled);
    sellerApiSubOrder(sellerApiUser()->store, SubOrderStatus::Confirmed); // another store

    // Three products from the sub-orders above (all live), one draft, one live with low stock.
    Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Draft]);
    $low = Product::factory()->create(['store_id' => $store->id]);
    $low->variants->first()->update(['stock' => 2]);
    $store->products()->get()->each(fn (Product $product) => $product->id === $low->id || $product->variants()->update(['stock' => 50]));

    $store->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 20000, 'status' => LedgerEntryStatus::Available, 'description' => 'Sale']);
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Commission, 'amount_sen' => -1000, 'status' => LedgerEntryStatus::Available, 'description' => 'Commission']);

    $response = sellerApiAs($seller)->getJson('/api/v1/seller/dashboard')
        ->assertOk()
        ->assertJsonPath('data.today_orders', 3)
        ->assertJsonPath('data.today_sales_sen', 21000) // confirmed + shipped; cancelled excluded
        ->assertJsonPath('data.orders_by_status.confirmed', 1)
        ->assertJsonPath('data.orders_by_status.shipped', 1)
        ->assertJsonPath('data.orders_by_status.cancelled', 1)
        ->assertJsonPath('data.orders_by_status.completed', 0)
        ->assertJsonPath('data.to_ship', 1)
        ->assertJsonPath('data.live_products', 4)
        ->assertJsonPath('data.low_stock', 1)
        ->assertJsonPath('data.available_sen', 19000)
        ->assertJsonPath('data.held_sen', 0)
        ->assertJsonCount(3, 'data.recent_orders');

    expect(collect($response->json('data.recent_orders'))->pluck('id'))->toContain($confirmed->id)
        ->and($response->json('data.recent_orders.0'))->toHaveKeys(['id', 'sub_order_no', 'status', 'status_label', 'total_sen', 'buyer', 'placed_at']);
});
