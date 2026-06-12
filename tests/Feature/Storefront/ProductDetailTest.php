<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

it('renders a live product with its name and formatted price', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Sambal Nyet Berapi', 'ms' => 'Sambal Nyet Berapi'],
    ]);
    $product->variants()->first()->update(['price_sen' => 2490, 'sale_price_sen' => null, 'stock' => 50]);

    $this->get('/p/'.$product->slug)
        ->assertOk()
        ->assertSee('Sambal Nyet Berapi')
        ->assertSee('RM 24.90');
});

it('returns 404 for a draft product', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->get('/p/'.$product->slug)->assertNotFound();
});

it('resolves the variant and its price when option values are selected', function () {
    $product = Product::factory()->withVariants(colour: 2, size: 2)->create();
    $product->load(['options.values', 'variants']);

    $colour = $product->options->firstWhere('name', 'Colour');
    $size = $product->options->firstWhere('name', 'Size');
    $colourValue = $colour->values->first();
    $sizeValue = $size->values->first();

    $variant = ProductVariant::resolveByValues($product->variants, [$colourValue->id, $sizeValue->id]);
    $variant->update(['price_sen' => 123456, 'sale_price_sen' => null, 'stock' => 50]);

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->call('selectValue', $colour->id, $colourValue->id)
        ->assertSet('selectedVariantId', null)
        ->call('selectValue', $size->id, $sizeValue->id)
        ->assertSet('selectedVariantId', $variant->id)
        ->assertSee('RM 1,234.56');
});

it('disables out-of-stock option values without hiding them', function () {
    $product = Product::factory()->withVariants(colour: 2, size: 1)->create();
    $product->load(['options.values', 'variants']);

    $colour = $product->options->firstWhere('name', 'Colour');
    $size = $product->options->firstWhere('name', 'Size');
    $availableColour = $colour->values->first();
    $soldOutColour = $colour->values->last();

    ProductVariant::resolveByValues($product->variants, [$soldOutColour->id, $size->values->first()->id])
        ->update(['stock' => 0]);
    ProductVariant::resolveByValues($product->variants, [$availableColour->id, $size->values->first()->id])
        ->update(['stock' => 10]);

    $html = Livewire::test(ProductDetail::class, ['product' => $product->fresh()])
        ->assertSee($soldOutColour->value)
        ->html();

    // The sold-out colour chip renders with the disabled attribute; the available one does not.
    expect($html)->toMatch('/selectValue\('.$colour->id.', '.$soldOutColour->id.'\)"\s+disabled/');
    expect($html)->not->toMatch('/selectValue\('.$colour->id.', '.$availableColour->id.'\)"\s+disabled/');
});

it('adds the variant to the cart for a logged-in buyer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = $product->variants()->first();
    $variant->update(['stock' => 10, 'sale_price_sen' => null]);

    Livewire::actingAs($user)
        ->test(ProductDetail::class, ['product' => $product])
        ->call('addToCart', $variant->id, 2)
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->itemCount($user))->toBe(2);
});

it('buy now adds to the cart and redirects to the cart page', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = $product->variants()->first();
    $variant->update(['stock' => 10, 'sale_price_sen' => null]);

    Livewire::actingAs($user)
        ->test(ProductDetail::class, ['product' => $product])
        ->call('buyNow')
        ->assertRedirect(route('cart'));

    expect(app(CartService::class)->itemCount($user))->toBe(1);
});
