<?php

use App\Livewire\Admin\Orders\Index as AdminOrders;
use App\Livewire\Seller\Products\Index as SellerProducts;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// HB-G154: x-ui.table.footer prints the one summary; its pager must not print a second.
test('admin orders footer shows one summary and the pager still pages', function () {
    $this->seed(RoleSeeder::class);
    $admin = makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
    SubOrder::factory()->count(21)->create(['store_id' => Store::factory()->approved()->create()->id]);

    $page = Livewire::actingAs($admin)->test(AdminOrders::class)->assertSee('Rows per page');
    expect(substr_count($page->html(), 'Showing'))->toBe(1)
        ->and($page->html())->not->toContain('results');
    $page->call('gotoPage', 2)->assertSee('Showing 21 to 21 of 21');
});

test('seller products footer shows one summary', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);
    Product::factory()->count(21)->create(['store_id' => $store->id]);

    $page = Livewire::actingAs($seller)->test(SellerProducts::class);
    expect(substr_count($page->html(), 'Showing'))->toBe(1)
        ->and($page->html())->not->toContain('results');
});
