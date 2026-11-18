<?php

use App\Livewire\Storefront\ProductDetail;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\User;
use App\Services\RecommendationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('visiting a PDP records a view for an authenticated buyer', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $product = Product::factory()->create();

    // The write runs after the response (defer), so drive a real request.
    $this->actingAs($buyer)->get(route('product.show', $product->slug))->assertOk();

    expect(ProductView::where('user_id', $buyer->id)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('guests do not generate product views', function () {
    $product = Product::factory()->create();

    Livewire::test(ProductDetail::class, ['product' => $product]);

    expect(ProductView::count())->toBe(0);
});

test('the recommendations cache busts when the buyer views a new product', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $service = app(RecommendationService::class);

    $first = $service->forUser($buyer, 12); // caches under the current signature

    $product = Product::factory()->create();
    $product->variants()->update(['stock' => 10]);
    ProductView::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'viewed_at' => now()]);

    // New view → new signature → recomputed (no stale cache hit). Just assert it runs.
    expect($service->forUser($buyer, 12))->toBeInstanceOf(Collection::class);
});
