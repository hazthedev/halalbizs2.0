<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\Listing;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;

test('selecting an attribute value facets the listing to matching products', function () {
    $category = Category::factory()->create();

    $colour = Attribute::create(['name' => ['en' => 'Colour'], 'slug' => 'colour-'.uniqid(), 'is_filterable' => true]);
    $red = AttributeValue::create(['attribute_id' => $colour->id, 'value' => ['en' => 'Red']]);
    $blue = AttributeValue::create(['attribute_id' => $colour->id, 'value' => ['en' => 'Blue']]);
    $category->attributes()->attach($colour);

    $productA = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Live]);
    $productB = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Live]);
    $productA->variants->first()->update(['stock' => 10]);
    $productB->variants->first()->update(['stock' => 10]);
    $productA->attributeValues()->attach($red);
    $productB->attributeValues()->attach($blue);

    Livewire::test(Listing::class, ['category' => $category])
        ->assertViewHas('total', 2) // both before faceting
        ->assertViewHas('facetAttributes', fn ($facets) => $facets->pluck('id')->contains($colour->id))
        ->call('toggleAttr', $red->id)
        ->assertViewHas('total', 1) // only the Red product after faceting
        ->assertViewHas('products', fn ($products) => $products->pluck('id')->contains($productA->id)
            && ! $products->pluck('id')->contains($productB->id))
        ->call('toggleAttr', $red->id) // toggle off → back to both
        ->assertViewHas('total', 2);
});

test('faceting AND-combines across attributes', function () {
    $category = Category::factory()->create();

    $colour = Attribute::create(['name' => ['en' => 'Colour'], 'slug' => 'c-'.uniqid(), 'is_filterable' => true]);
    $size = Attribute::create(['name' => ['en' => 'Size'], 'slug' => 's-'.uniqid(), 'is_filterable' => true]);
    $red = AttributeValue::create(['attribute_id' => $colour->id, 'value' => ['en' => 'Red']]);
    $large = AttributeValue::create(['attribute_id' => $size->id, 'value' => ['en' => 'L']]);
    $category->attributes()->attach([$colour->id, $size->id]);

    // A = Red+L, B = Red only.
    $productA = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Live]);
    $productB = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Live]);
    $productA->variants->first()->update(['stock' => 10]);
    $productB->variants->first()->update(['stock' => 10]);
    $productA->attributeValues()->attach([$red->id, $large->id]);
    $productB->attributeValues()->attach([$red->id]);

    Livewire::test(Listing::class, ['category' => $category])
        ->call('toggleAttr', $red->id)
        ->assertViewHas('total', 2)
        ->call('toggleAttr', $large->id) // Red AND L → only A
        ->assertViewHas('total', 1)
        ->assertViewHas('products', fn ($products) => $products->pluck('id')->contains($productA->id)
            && ! $products->pluck('id')->contains($productB->id));
});
