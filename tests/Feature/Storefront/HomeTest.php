<?php

use App\Livewire\Storefront\Home;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use Database\Seeders\HomeSectionSeeder;
use Livewire\Livewire;

it('renders the home page with seeded sections and a live product', function () {
    $this->seed(HomeSectionSeeder::class);

    $category = Category::factory()->create(['name' => ['en' => 'Snacks & Treats', 'ms' => 'Snek & Manisan']]);
    Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Pure Sabah Honey', 'ms' => 'Madu Sabah Asli'],
        'sold_count' => 999,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Shop by category')
        ->assertSee('Snacks & Treats')
        ->assertSee('New on the market')
        ->assertSee('Popular now')
        ->assertSee('Pure Sabah Honey');
});

it('renders the home page when no sections are seeded', function () {
    $this->get('/')->assertOk();
});

it('shows recently viewed products once ids are hydrated from localStorage', function () {
    HomeSection::create([
        'type' => 'recently_viewed',
        'title' => ['en' => 'Recently viewed', 'ms' => 'Baru dilihat'],
        'position' => 0,
        'is_active' => true,
    ]);

    $product = Product::factory()->create(['name' => ['en' => 'Halal Beef Jerky', 'ms' => 'Dendeng Daging Halal']]);

    Livewire::test(Home::class)
        ->assertDontSee('Halal Beef Jerky')
        ->call('loadRecentlyViewed', [$product->id])
        ->assertSee('Recently viewed')
        ->assertSee('Halal Beef Jerky');
});
