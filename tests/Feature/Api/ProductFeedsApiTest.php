<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\User;
use App\Services\RecommendationService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('related is the web strip: same category, live, not itself, newest first, max 6', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $others = Product::factory()->count(7)->create(['category_id' => $category->id]);
    Product::factory()->create(); // other category

    $expected = app(RecommendationService::class)->related($product)->pluck('id')->all();

    $res = $this->getJson("/api/v1/products/{$product->slug}/related")->assertOk();
    expect($res->json('data.*.id'))->toBe($expected)->toHaveCount(6)->not->toContain($product->id);
    expect(Product::whereIn('id', $res->json('data.*.id'))->pluck('category_id')->unique()->all())->toBe([$category->id]);
    expect($others)->toHaveCount(7);
});

test('related 404s for a product that is not live', function () {
    $product = Product::factory()->create();
    $product->forceFill(['status' => 'draft'])->save();

    $this->getJson("/api/v1/products/{$product->slug}/related")->assertNotFound();
});

test('recommended: a guest with no viewed ids gets nothing (web home hides the popular fallback)', function () {
    Product::factory()->count(3)->create(['sold_count' => 10]);

    $this->getJson('/api/v1/recommended')->assertOk()->assertJsonPath('data', []);
});

test('recommended: guest viewed ids and a buyer token both go through the web service', function () {
    $category = Category::factory()->create();
    $viewed = Product::factory()->create(['category_id' => $category->id]);
    $sameCat = Product::factory()->create(['category_id' => $category->id]);
    $service = app(RecommendationService::class);

    $guest = $this->getJson('/api/v1/recommended?viewed[]='.$viewed->id)->assertOk();
    expect($guest->json('data.*.id'))->toBe($service->forContext(null, [$viewed->id], true)->pluck('id')->all());

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    ProductView::create(['user_id' => $buyer->id, 'product_id' => $viewed->id, 'viewed_at' => now()]);
    app('auth')->forgetGuards();

    $mine = $this->withToken($buyer->createToken('pest')->plainTextToken)->getJson('/api/v1/recommended')->assertOk();
    expect($mine->json('data.*.id'))->toBe($service->forContext($buyer, [], true)->pluck('id')->all())->toContain($sameCat->id);
});
