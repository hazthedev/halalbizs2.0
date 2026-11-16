<?php

use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

test('the local COD fixture creates a product and can be run again', function (array $target) {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Category::factory()->create();
    config(['database.connections.'.config('database.default') => $target]);

    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan('e2e:cod-fixture')->expectsOutput('cod-journey-widget')->assertSuccessful();
        $this->artisan('e2e:cod-fixture')->expectsOutput('cod-journey-widget')->assertSuccessful();
    } finally {
        app()['env'] = $environment;
    }
})->with([
    'in-memory Pest' => [['driver' => 'sqlite', 'database' => ':memory:']],
    'local QA' => [['driver' => 'mariadb', 'host' => '127.0.0.1', 'database' => 'hbiz2_qa']],
    'local QA URL' => [['driver' => 'mysql', 'url' => 'mysql://localhost/hbiz2_qa']],
]);

test('the COD fixture uses the selected seller and clears only the selected buyer cart', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Category::factory()->create();
    $selected = User::factory()->create(['email' => 'e2e-selected@halalbizs.test']);
    $other = User::factory()->create(['email' => 'buyer@halalbizs.test']);
    $variant = Product::factory()->create()->variants()->firstOrFail();
    foreach ([$selected, $other] as $buyer) {
        $buyer->cart()->create()->items()->create(['product_variant_id' => $variant->id, 'qty' => 1]);
    }

    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan('e2e:cod-fixture', [
            '--seller' => 'e2e-seller@halalbizs.test', '--buyer' => $selected->email,
        ])->assertSuccessful();
        expect(User::where('email', 'e2e-seller@halalbizs.test')->firstOrFail()->store)->not->toBeNull()
            ->and($selected->cart->items()->count())->toBe(0)
            ->and($other->cart->items()->count())->toBe(1);
    } finally {
        app()['env'] = $environment;
    }
});

test('the sale seeder completes an order for the selected fixture seller', function () {
    $this->seed(RoleSeeder::class);
    Category::factory()->create();
    $buyer = User::factory()->create();
    Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    Store::factory()->approved()->create(['name' => 'COD Journey Store']);

    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan('e2e:cod-fixture', ['--seller' => 'e2e-sale@halalbizs.test', '--buyer' => $buyer->email])
            ->assertSuccessful();
        $this->artisan('e2e:seed-sale', ['email' => $buyer->email, '--seller' => 'e2e-sale@halalbizs.test'])
            ->assertSuccessful();
        $order = Order::sole();
        $subOrder = $order->subOrders()->sole();
        expect($order->user_id)->toBe($buyer->id)
            ->and($subOrder->store_id)->toBe(User::where('email', 'e2e-sale@halalbizs.test')->firstOrFail()->store->id)
            ->and($subOrder->status)->toBe(SubOrderStatus::Completed)
            ->and($subOrder->statusHistories()->count())->toBe(5);
    } finally {
        app()['env'] = $environment;
    }
});
