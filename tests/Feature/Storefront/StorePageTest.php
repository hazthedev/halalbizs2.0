<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;

it('renders an approved store page with the store name', function () {
    $store = Store::factory()->approved()->create(['name' => 'Kedai Pak Mat']);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('Kedai Pak Mat');
});

it('returns 404 for a pending store', function () {
    $store = Store::factory()->create();

    $this->get('/s/'.$store->slug)->assertNotFound();
});

it('shows the holiday banner when holiday mode is active', function () {
    $store = Store::factory()->approved()->create(['holiday_mode' => true]);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('This shop is on holiday — orders are paused.');
});

it('lists only the store\'s live products', function () {
    $store = Store::factory()->approved()->create();
    $live = Product::factory()->create([
        'store_id' => $store->id,
        'name' => ['en' => 'Visible Product Here', 'ms' => 'Visible Product Here'],
    ]);
    Product::factory()->create([
        'store_id' => $store->id,
        'status' => ProductStatus::Draft,
        'name' => ['en' => 'Hidden Draft Product', 'ms' => 'Hidden Draft Product'],
    ]);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('Visible Product Here')
        ->assertDontSee('Hidden Draft Product');
});
