<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\ProductReviews;
use App\Livewire\Storefront\RelatedProducts;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use Livewire\Features\SupportLazyLoading\SupportLazyLoading;
use Livewire\Livewire;

function polishLazyReview(Product $product, string $comment): Review
{
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create([
        'order_id' => $order->id,
        'store_id' => $product->store_id,
    ]);

    $item = $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    return Review::create([
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'store_id' => $subOrder->store_id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'comment' => $comment,
    ]);
}

test('the lazy ProductReviews child renders review content after load', function () {
    $product = Product::factory()->create();
    polishLazyReview($product, 'Lazy loaded review body renders fine.');

    Livewire::test(ProductReviews::class, ['product' => $product->fresh()])
        ->assertSee('Lazy loaded review body renders fine.')
        ->call('setReviewFilter', 'photos')
        ->assertSee('No reviews match this filter yet.');
});

test('the lazy RelatedProducts child renders sibling products after load', function () {
    $product = Product::factory()->create();
    $related = Product::factory()->create([
        'category_id' => $product->category_id,
        'name' => ['en' => 'Related Pandan Cake', 'ms' => 'Related Pandan Cake'],
    ]);

    Livewire::test(RelatedProducts::class, ['product' => $product])
        ->assertSee('Related products')
        ->assertSee('Related Pandan Cake');
});

test('with lazy loading active the PDP serves skeleton placeholders, not review queries', function () {
    // TestCase disables lazy loading suite-wide; re-enable it for this one
    // request to prove the placeholder path (matching-layout skeletons).
    SupportLazyLoading::$disableWhileTesting = false;

    $product = Product::factory()->create();
    polishLazyReview($product, 'This comment must not be in the first response.');

    $this->get('/p/'.$product->slug)
        ->assertOk()
        ->assertSee('shimmer', false)              // skeleton shimmer blocks
        ->assertSee('__lazyLoad', false)           // Livewire lazy hydration hook
        ->assertDontSee('This comment must not be in the first response.');
});
