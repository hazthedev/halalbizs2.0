<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\Listing;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use Livewire\Livewire;

beforeEach(function () {
    // phpunit.xml pins SCOUT_DRIVER=null for the suite; the listing's search
    // entry depends on the collection engine, so opt in per-test.
    config(['scout.driver' => 'collection']);
});

test('category page renders a live product in that category', function () {
    $category = Category::factory()->create();

    Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Halal Honey Jar', 'ms' => 'Halal Honey Jar'],
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Draft,
        'name' => ['en' => 'Hidden Draft Gadget', 'ms' => 'Hidden Draft Gadget'],
    ]);

    $this->get(route('category.show', $category->slug))
        ->assertOk()
        ->assertSee('Halal Honey Jar')
        ->assertDontSee('Hidden Draft Gadget');
});

test('category page shows products from descendant categories', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Product::factory()->create([
        'category_id' => $child->id,
        'name' => ['en' => 'Nested Prayer Mat', 'ms' => 'Nested Prayer Mat'],
    ]);

    $this->get(route('category.show', $parent->slug))
        ->assertOk()
        ->assertSee('Nested Prayer Mat');
});

test('search page logs the search and renders matching results', function () {
    Product::factory()->create(['name' => ['en' => 'Qberry Jam Deluxe', 'ms' => 'Qberry Jam Deluxe']]);
    Product::factory()->create(['name' => ['en' => 'Plain Leather Wallet', 'ms' => 'Plain Leather Wallet']]);

    $this->get(route('search', ['q' => 'qberry']))
        ->assertOk()
        ->assertSee('Qberry Jam Deluxe')
        ->assertDontSee('Plain Leather Wallet');

    expect(SearchLog::where('term', 'qberry')->count())->toBe(1)
        ->and(SearchLog::where('term', 'qberry')->first()->results_count)->toBe(1);
});

test('price filter excludes products outside the RM range', function () {
    $category = Category::factory()->create();

    $cheap = Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Budget Tea Sampler', 'ms' => 'Budget Tea Sampler'],
    ]);
    $cheap->variants()->update(['price_sen' => 1_000, 'sale_price_sen' => null]); // RM 10

    $pricey = Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Premium Oud Perfume', 'ms' => 'Premium Oud Perfume'],
    ]);
    $pricey->variants()->update(['price_sen' => 50_000, 'sale_price_sen' => null]); // RM 500

    $this->get(route('category.show', ['category' => $category->slug, 'price_min' => 100, 'price_max' => 600]))
        ->assertOk()
        ->assertSee('Premium Oud Perfume')
        ->assertDontSee('Budget Tea Sampler');
});

test('sort by price ascending orders products cheapest first', function () {
    $category = Category::factory()->create();

    $expensive = Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Bravo Carved Cabinet', 'ms' => 'Bravo Carved Cabinet'],
    ]);
    $expensive->variants()->update(['price_sen' => 90_000, 'sale_price_sen' => null]);

    $cheap = Product::factory()->create([
        'category_id' => $category->id,
        'name' => ['en' => 'Alpha Pocket Comb', 'ms' => 'Alpha Pocket Comb'],
    ]);
    $cheap->variants()->update(['price_sen' => 500, 'sale_price_sen' => null]);

    $this->get(route('category.show', ['category' => $category->slug, 'sort' => 'price_asc']))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Pocket Comb', 'Bravo Carved Cabinet']);

    $this->get(route('category.show', ['category' => $category->slug, 'sort' => 'price_desc']))
        ->assertOk()
        ->assertSeeInOrder(['Bravo Carved Cabinet', 'Alpha Pocket Comb']);
});

test('load more increments the visible product count', function () {
    $category = Category::factory()->create();

    Product::factory()->count(30)->create(['category_id' => $category->id]);

    Livewire::test(Listing::class, ['category' => $category])
        ->assertViewHas('products', fn ($products) => $products->count() === 24)
        ->assertViewHas('hasMore', true)
        ->call('loadMore')
        ->assertViewHas('products', fn ($products) => $products->count() === 30)
        ->assertViewHas('hasMore', false);
});

test('filters reset paging and sync to the query string', function () {
    $category = Category::factory()->create();

    Product::factory()->count(30)->create([
        'category_id' => $category->id,
        'cod_enabled' => true,
    ]);

    Livewire::withQueryParams(['cod' => true])
        ->test(Listing::class, ['category' => $category])
        ->assertSet('cod', true)
        ->call('loadMore')
        ->assertSet('perPage', 48)
        ->set('state', 'Selangor')
        ->assertSet('perPage', 24);
});
