<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\RecommendationService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function recBuyer(): User
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return $buyer;
}

/** A live, in-stock product in a given category. */
function recProduct(Category $category, int $sold = 0): Product
{
    // recBuy() always checks out via COD, so force cod_enabled (factory rolls it
    // at 80%) and a low price (factory price can exceed the RM500 COD cap) — both
    // made this suite intermittently fail.
    $product = Product::factory()->create(['category_id' => $category->id, 'sold_count' => $sold, 'cod_enabled' => true]);
    $product->variants()->update(['stock' => 50, 'sale_price_sen' => null, 'price_sen' => 5000]);

    return $product;
}

/** Walk a completed purchase of $product by $buyer. */
function recBuy(User $buyer, Product $product): void
{
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($buyer, $buyer->addresses->first(), PaymentMethod::Cod);
    $so = $order->subOrders->first();
    $status = app(SubOrderStatusService::class);
    $status->transition($so, SubOrderStatus::Processing, ActorType::Seller);
    $status->transition($so->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($so->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($so->fresh(), $buyer->id);
}

test('purchases drive recommendations from the same category, excluding the bought item', function () {
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();

    $bought = recProduct($catA);
    $sibling = recProduct($catA, sold: 100); // a NEW product in the bought category
    $unrelated = recProduct($catB, sold: 9999);

    $buyer = recBuyer();
    recBuy($buyer, $bought);

    $recs = app(RecommendationService::class)->forUser($buyer, 12);
    $ids = $recs->pluck('id');

    expect($ids)->toContain($sibling->id)        // same-category discovery
        ->and($ids)->not->toContain($bought->id); // never re-recommend what they bought
});

test('viewed-product categories influence recommendations', function () {
    $cat = Category::factory()->create();
    $viewedProduct = recProduct($cat);
    $similar = recProduct($cat, sold: 50);

    $buyer = recBuyer();
    ProductView::create(['user_id' => $buyer->id, 'product_id' => $viewedProduct->id, 'viewed_at' => now()]);

    $recs = app(RecommendationService::class)->forUser($buyer, 12);

    expect($recs->pluck('id'))->toContain($similar->id)
        ->and($recs->pluck('id'))->not->toContain($viewedProduct->id);
});

test('wishlisted items are excluded from output but their category counts', function () {
    $cat = Category::factory()->create();
    $wished = recProduct($cat);
    $similar = recProduct($cat, sold: 30);

    $buyer = recBuyer();
    Wishlist::create(['user_id' => $buyer->id, 'product_id' => $wished->id]);

    $recs = app(RecommendationService::class)->forUser($buyer, 12);

    expect($recs->pluck('id'))->toContain($similar->id)
        ->and($recs->pluck('id'))->not->toContain($wished->id);
});

test('only live, in-stock products are recommended', function () {
    $cat = Category::factory()->create();
    $signal = recProduct($cat);

    $outOfStock = recProduct($cat, sold: 500);
    $outOfStock->variants()->update(['stock' => 0]);

    $draft = recProduct($cat, sold: 500);
    $draft->update(['status' => ProductStatus::Draft]);

    $buyer = recBuyer();
    ProductView::create(['user_id' => $buyer->id, 'product_id' => $signal->id, 'viewed_at' => now()]);

    $ids = app(RecommendationService::class)->forUser($buyer, 12)->pluck('id');

    expect($ids)->not->toContain($outOfStock->id)
        ->and($ids)->not->toContain($draft->id);
});

test('cold-start buyer with no history gets popular products', function () {
    $cat = Category::factory()->create();
    $popular = recProduct($cat, sold: 9999);
    recProduct($cat, sold: 1);

    $recs = app(RecommendationService::class)->forUser(recBuyer(), 12);

    expect($recs)->not->toBeEmpty()
        ->and($recs->first()->id)->toBe($popular->id); // most sold leads
});

test('the excludeProductId is never recommended (PDP self-exclusion)', function () {
    $cat = Category::factory()->create();
    $current = recProduct($cat, sold: 9999);
    recProduct($cat, sold: 10);

    $buyer = recBuyer();
    ProductView::create(['user_id' => $buyer->id, 'product_id' => $current->id, 'viewed_at' => now()]);

    $ids = app(RecommendationService::class)->forUser($buyer, 12, $current->id)->pluck('id');

    expect($ids)->not->toContain($current->id);
});

test('guest forViewedIds path recommends from viewed categories, else popular', function () {
    $cat = Category::factory()->create();
    $viewed = recProduct($cat);
    $similar = recProduct($cat, sold: 40);

    $service = app(RecommendationService::class);

    expect($service->forViewedIds([$viewed->id], 12)->pluck('id'))
        ->toContain($similar->id)
        ->not->toContain($viewed->id);

    // No ids → popular fallback, no crash.
    expect($service->forViewedIds([], 12))->not->toBeEmpty();
});

test('ProductView upsert dedupes on repeat views and bumps recency', function () {
    $buyer = recBuyer();
    $product = recProduct(Category::factory()->create());

    ProductView::updateOrCreate(['user_id' => $buyer->id, 'product_id' => $product->id], ['viewed_at' => now()->subDay()]);
    ProductView::updateOrCreate(['user_id' => $buyer->id, 'product_id' => $product->id], ['viewed_at' => now()]);

    expect(ProductView::where('user_id', $buyer->id)->where('product_id', $product->id)->count())->toBe(1);
});
