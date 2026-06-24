<?php

use App\Enums\PaymentMethod;
use App\Livewire\Storefront\ProductReviews;
use App\Models\Address;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\RecommendationService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function reviewBuyer(): User
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    return $buyer;
}

function reviewFor(Product $product, User $reviewer): Review
{
    $address = Address::factory()->default()->create(['user_id' => $reviewer->id, 'state' => 'Selangor']);
    app(CartService::class)->addItem($reviewer, $product->variants->first(), 1);
    $order = app(CheckoutService::class)->place($reviewer, $address, PaymentMethod::Cod);

    return Review::create([
        'order_item_id' => $order->subOrders->first()->items->first()->id,
        'product_id' => $product->id,
        'store_id' => $product->store_id,
        'user_id' => $reviewer->id,
        'rating' => 5,
        'comment' => 'Great product.',
    ]);
}

test('a review from a purchase is marked a verified purchase', function () {
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    $review = reviewFor($product, reviewBuyer());

    expect($review->isVerifiedPurchase())->toBeTrue();

    Livewire::test(ProductReviews::class, ['product' => $product])
        ->assertSee('Verified purchase');
});

test('a buyer can vote a review helpful once and toggle it off', function () {
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    $review = reviewFor($product, reviewBuyer());
    $voter = reviewBuyer();

    Livewire::actingAs($voter)
        ->test(ProductReviews::class, ['product' => $product])
        ->call('markHelpful', $review->id);

    expect($review->fresh()->helpful_count)->toBe(1);

    Livewire::actingAs($voter)
        ->test(ProductReviews::class, ['product' => $product])
        ->call('markHelpful', $review->id); // toggle off

    expect($review->fresh()->helpful_count)->toBe(0);
});

test('frequently bought together surfaces co-purchased products', function () {
    $buyer = reviewBuyer();
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $a = Product::factory()->create(['cod_enabled' => true]);
    $b = Product::factory()->for($a->store)->create(['cod_enabled' => true]); // same store → one order
    $a->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);
    $b->variants->first()->update(['price_sen' => 1000, 'sale_price_sen' => null, 'stock' => 10]);

    app(CartService::class)->addItem($buyer, $a->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $b->variants->first(), 1);
    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    $fbt = app(RecommendationService::class)->frequentlyBoughtTogether($a->id);

    expect($fbt->pluck('id')->all())->toContain($b->id)
        ->and($fbt->pluck('id')->all())->not->toContain($a->id);
});
