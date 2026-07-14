<?php

use App\Livewire\Storefront\Landing;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;

it('lets a guest view the landing page with the core buyer + seller pitch', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertSeeLivewire(Landing::class)
        ->assertSee('Shop Now')
        ->assertSee('Start Selling')
        ->assertSee('Why shop HalalBizs')
        ->assertSee('How buying works')
        ->assertSee('Open your store on HalalBizs');
});

it('shows real top-level categories when they are seeded', function () {
    Category::factory()->create(['name' => ['en' => 'Snacks & Treats', 'ms' => 'Snek & Manisan']]);

    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Shop by category')
        ->assertSee('Snacks & Treats');
});

it('falls back to a static category preview when none are seeded', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Shop by category')
        ->assertSee('Groceries & Pantry');
});

it('shows the stats band with real counts', function () {
    Store::factory()->approved()->create();
    Product::factory()->create();
    Category::factory()->create();

    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Active local sellers')
        ->assertSee('Products listed')
        ->assertSee('Categories to explore')
        ->assertSeeInOrder(['data-countup', 'data-target']);
});
