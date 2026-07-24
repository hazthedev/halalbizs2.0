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
        ->assertSee('Open your stall in the souk');
});

it('renders all seven Night Souk section landmarks', function () {
    $response = $this->get('/welcome')->assertOk();

    foreach (['hero', 'trust', 'categories', 'how', 'stats', 'seller', 'footer-cta'] as $key) {
        $response->assertSee('data-land="'.$key.'"', false);
    }
});

it('carries the word/eyebrow/subcopy/cta-row/item motion hooks with zero JS required to read them', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertSee('data-motion="eyebrow"', false)
        ->assertSee('data-motion="word"', false)
        ->assertSee('data-motion="subcopy"', false)
        ->assertSee('data-motion="cta-row"', false)
        ->assertSee('data-motion="item"', false)
        ->assertSee('id="how-path"', false);
});

it('has no storefront shopping chrome — no search bar, cart or category nav', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertDontSee('Search products, stores', false)
        ->assertDontSee('open-mini-cart', false)
        ->assertDontSee('open-search', false);
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

it('shows the stats band with real, server-formatted counts', function () {
    Store::factory()->approved()->create();
    Product::factory()->create();
    Category::factory()->create();

    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Active local sellers')
        ->assertSee('Products listed')
        ->assertSee('Categories to explore')
        ->assertSeeInOrder(['data-countup', 'data-target'])
        ->assertSee('data-target="1"', false);
});
