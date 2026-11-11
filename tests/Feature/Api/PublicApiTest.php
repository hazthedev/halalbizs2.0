<?php

use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\HomeSection;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('home has featured products, top-level categories, the live flash sale and banners', function () {
    HomeSection::create(['type' => 'product_grid', 'title' => ['en' => 'Popular now'], 'payload' => ['source' => 'top', 'limit' => 2], 'position' => 1, 'is_active' => true]);
    $top = Product::factory()->create(['sold_count' => 900]);
    Product::factory()->create(['sold_count' => 5]);
    Product::factory()->create(['sold_count' => 50]);
    $parent = Category::factory()->create(['parent_id' => null, 'is_active' => true]);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);

    $sale = FlashSale::create(['title' => 'Lunch deal', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    $sale->items()->create(['product_variant_id' => $top->variants->first()->id, 'promo_price_sen' => 500, 'allocated_qty' => 10, 'per_buyer_limit' => 2, 'sold_qty' => 3]);

    $this->getJson('/api/v1/home')
        ->assertOk()
        ->assertJsonCount(2, 'data.featured')
        ->assertJsonPath('data.featured.0.id', $top->id)
        ->assertJsonMissing(['id' => $child->id, 'parent_id' => $parent->id])
        ->assertJsonPath('data.flash_sale.products.0.id', $top->id)
        ->assertJsonPath('data.flash_sale.products.0.flash.price_sen', 500)
        ->assertJsonPath('data.flash_sale.products.0.flash.remaining', 7)
        ->assertJsonPath('data.banners', [])
        ->assertJsonStructure(['data' => ['flash_sale' => ['ends_at', 'products']]]);

    expect($this->getJson('/api/v1/home')->json('data.categories.*.id'))->toContain($parent->id)->not->toContain($child->id);
});

test('store page and its products; pending stores are a 404', function () {
    $store = Store::factory()->approved()->create();
    $cheap = Product::factory()->create(['store_id' => $store->id, 'sold_count' => 1]);
    $dear = Product::factory()->create(['store_id' => $store->id, 'sold_count' => 99]);
    $cheap->variants->first()->update(['price_sen' => 100, 'sale_price_sen' => null]);
    $dear->variants->first()->update(['price_sen' => 9900, 'sale_price_sen' => null]);

    $this->getJson("/api/v1/stores/{$store->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $store->id)
        ->assertJsonPath('data.slug', $store->slug)
        ->assertJsonPath('data.product_count', 2)
        ->assertJsonPath('data.review_count', 0)
        ->assertJsonStructure(['data' => ['name', 'description', 'logo', 'banner', 'rating_avg', 'joined_at']]);

    $this->getJson("/api/v1/stores/{$store->slug}/products?sort=price_desc")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $dear->id);

    $this->getJson("/api/v1/stores/{$store->slug}/products?sort=top")->assertJsonPath('data.0.id', $dear->id);
    $this->getJson("/api/v1/stores/{$store->slug}/products?sort=price_asc")->assertJsonPath('data.0.id', $cheap->id);
    $this->getJson("/api/v1/stores/{$store->slug}/products?sort=bogus")->assertStatus(422);

    $pending = Store::factory()->create(['status' => StoreStatus::Pending]);
    $this->getJson("/api/v1/stores/{$pending->slug}")->assertNotFound();
    $this->getJson("/api/v1/stores/{$pending->slug}/products")->assertNotFound();
});

test('product reviews list the visible ones with the masked buyer name and seller reply', function () {
    $product = Product::factory()->create();
    $buyer = User::factory()->create(['name' => 'Nurul Aina']);
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->create(['order_id' => $order->id, 'store_id' => $product->store_id]);

    $makeReview = function (int $rating, bool $hidden = false) use ($product, $buyer, $subOrder) {
        $item = $subOrder->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $product->variants->first()->id,
            'product_name' => 'x', 'unit_price_sen' => 100, 'qty' => 1, 'line_total_sen' => 100,
        ]);

        return Review::create([
            'order_item_id' => $item->id, 'product_id' => $product->id, 'store_id' => $product->store_id, 'user_id' => $buyer->id,
            'rating' => $rating, 'comment' => "Rated {$rating}", 'is_hidden' => $hidden,
            'seller_reply' => $rating === 5 ? 'Thank you!' : null, 'seller_replied_at' => $rating === 5 ? now() : null,
        ]);
    };

    $makeReview(5);
    $makeReview(3);
    $makeReview(1, hidden: true);

    $this->getJson("/api/v1/products/{$product->slug}/reviews")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.rating', 3)
        ->assertJsonPath('data.0.buyer_name', 'Nurul A.')
        ->assertJsonPath('data.0.seller_reply', null)
        ->assertJsonPath('data.1.seller_reply.body', 'Thank you!')
        ->assertJsonPath('data.1.photos', [])
        ->assertJsonStructure(['data' => [['id', 'rating', 'body', 'buyer_name', 'photos', 'created_at', 'seller_reply']]]);

    // Same filter keys as the web reviews tab.
    $this->getJson("/api/v1/products/{$product->slug}/reviews?filter=5")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.rating', 5);
    $this->getJson("/api/v1/products/{$product->slug}/reviews?filter=photos")->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson("/api/v1/products/{$product->slug}/reviews?filter=bogus")->assertOk()->assertJsonPath('meta.total', 2);
});

test('products filters: category with descendants, store, price band and sort', function () {
    $parent = Category::factory()->create(['is_active' => true]);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
    $store = Store::factory()->approved()->create();

    $inChild = Product::factory()->create(['category_id' => $child->id, 'store_id' => $store->id, 'sold_count' => 10]);
    $inParent = Product::factory()->create(['category_id' => $parent->id, 'sold_count' => 20]);
    $elsewhere = Product::factory()->create(['sold_count' => 30]);
    $inChild->variants->first()->update(['price_sen' => 500]);
    $inParent->variants->first()->update(['price_sen' => 5000]);
    $elsewhere->variants->first()->update(['price_sen' => 50000]);

    expect($this->getJson("/api/v1/products?category={$parent->slug}&sort=top")->assertOk()->json('data.*.id'))->toBe([$inParent->id, $inChild->id]);
    expect($this->getJson("/api/v1/products?store={$store->slug}")->json('data.*.id'))->toBe([$inChild->id]);
    expect($this->getJson('/api/v1/products?min_sen=1000&max_sen=10000')->json('data.*.id'))->toBe([$inParent->id]);
    expect($this->getJson('/api/v1/products?sort=price_desc')->json('data.0.id'))->toBe($elsewhere->id);
    $this->getJson('/api/v1/products?category=no-such-category')->assertNotFound();
});

test('search applies the category and sort filters like products does', function () {
    $rice = Category::factory()->create(['is_active' => true]);
    $other = Category::factory()->create(['is_active' => true]);
    $mid = Product::factory()->create(['name' => ['en' => 'Aut rice mid'], 'category_id' => $rice->id, 'sold_count' => 5]);
    $cheap = Product::factory()->create(['name' => ['en' => 'Aut rice cheap'], 'category_id' => $rice->id, 'sold_count' => 1]);
    $dear = Product::factory()->create(['name' => ['en' => 'Aut oil dear'], 'category_id' => $other->id, 'sold_count' => 9]);
    $mid->variants->first()->update(['price_sen' => 15061]);
    $cheap->variants->first()->update(['price_sen' => 3124]);
    $dear->variants->first()->update(['price_sen' => 35442]);

    expect($this->getJson('/api/v1/search?q=aut&sort=price_asc')->assertOk()->json('data.*.id'))->toBe([$cheap->id, $mid->id, $dear->id]);
    expect($this->getJson('/api/v1/search?q=aut&sort=price_desc')->json('data.*.id'))->toBe([$dear->id, $mid->id, $cheap->id]);
    expect($this->getJson('/api/v1/search?q=aut&sort=top')->json('data.*.id'))->toBe([$dear->id, $mid->id, $cheap->id]);
    expect($this->getJson("/api/v1/search?q=aut&category={$rice->slug}&sort=price_asc")->json('data.*.id'))->toBe([$cheap->id, $mid->id]);
    $this->getJson('/api/v1/search?q=aut&sort=popular')->assertUnprocessable();
});

test('product detail lists every image in the seller order with large and card urls', function () {
    Storage::fake('public');
    $product = Product::factory()->create();
    foreach (['one', 'two', 'three'] as $name) {
        $product->addMedia(UploadedFile::fake()->image("{$name}.jpg", 50, 50))->toMediaCollection('images');
    }
    $media = $product->getMedia('images');
    Media::setNewOrder([$media[2]->id, $media[0]->id, $media[1]->id]);

    $images = $this->getJson("/api/v1/products/{$product->slug}")->assertOk()->json('data.images');

    expect($images)->toHaveCount(3)
        ->and(collect($images)->pluck('large')->map(fn ($u) => basename($u))->all())->toBe(['three.jpg', 'one.jpg', 'two.jpg'])
        ->and($images[0])->toHaveKeys(['large', 'card']);
    expect($this->getJson('/api/v1/products')->json('data.0'))->toHaveKey('image')->not->toHaveKey('images');
});

test('product detail exposes option groups, values and each variant option_value_ids', function () {
    $product = Product::factory()->withVariants(colour: 2, size: 2)->create();
    $product->load('options.values', 'variants');
    $blueM = $product->variants->firstWhere('options_label', 'Blue / M');
    $blueM->update(['price_sen' => 7740, 'sale_price_sen' => 7000, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]);

    $data = $this->getJson("/api/v1/products/{$product->slug}")->assertOk()->json('data');

    expect(collect($data['options'])->pluck('name')->all())->toBe(['Colour', 'Size'])
        ->and(collect($data['options'][0]['values'])->pluck('value')->all())->toBe(['Red', 'Blue'])
        ->and(collect($data['options'][1]['values'])->pluck('value')->all())->toBe(['S', 'M']);
    $variant = collect($data['variants'])->firstWhere('id', $blueM->id);
    $blue = $data['options'][0]['values'][1]['id'];
    $m = $data['options'][1]['values'][1]['id'];
    expect($variant['option_value_ids'])->toBe([$blue, $m])
        ->and($variant['price_sen'])->toBe(7000)
        ->and($variant['regular_price_sen'])->toBe(7740);
});
