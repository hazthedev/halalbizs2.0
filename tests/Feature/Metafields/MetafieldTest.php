<?php

use App\Enums\StoreStatus;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['metafields.enabled' => true, 'scout.driver' => 'collection']);
});

function metafieldSeller(): User
{
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    return $seller;
}

test('a seller saves trust metafields when creating a product', function () {
    $seller = metafieldSeller();
    $category = Category::factory()->create();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Acacia Halal Honey')
        ->set('categoryTop', $category->id)
        ->set('price', '19.90')
        ->set('stock', '12')
        ->set('sku', 'HNY-1')
        ->set('metafields.halal_cert_body', 'JAKIM')
        ->set('metafields.ingredients', 'Pure acacia honey')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::where('store_id', $seller->store->id)->first();
    expect($product->metafield('halal_cert_body'))->toBe('JAKIM')
        ->and($product->metafield('ingredients'))->toBe('Pure acacia honey');
});

test('the edit form loads existing metafields and clearing one removes it', function () {
    $seller = metafieldSeller();
    $product = Product::factory()->create(['store_id' => $seller->store->id]);
    $product->metafields()->create(['key' => 'halal_cert_body', 'value' => 'JAKIM']);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->assertSet('metafields.halal_cert_body', 'JAKIM')
        ->set('metafields.halal_cert_body', '')
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->fresh()->metafield('halal_cert_body'))->toBeNull();
});

test('metafields render on the product page', function () {
    $product = Product::factory()->create();
    $product->metafields()->create(['key' => 'halal_cert_body', 'value' => 'JAKIM']);
    $product->metafields()->create(['key' => 'sirim_number', 'value' => 'SR-12345']);

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->assertSee('JAKIM')
        ->assertSee('SR-12345');
});

test('a product with no metafields renders no trust panel', function () {
    $product = Product::factory()->create();

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->assertDontSee(__('Halal certification'));
});

test('searchable metafields are indexed and findable', function () {
    $honey = Product::factory()->create(['name' => ['en' => 'Generic Jar', 'ms' => 'Generic Jar']]);
    $honey->metafields()->create(['key' => 'ingredients', 'value' => 'kurma ajwa dates']);

    $wallet = Product::factory()->create(['name' => ['en' => 'Plain Wallet', 'ms' => 'Plain Wallet']]);

    $ids = Product::search('ajwa')->keys()->all();

    expect($ids)->toContain($honey->id)
        ->and($ids)->not->toContain($wallet->id);
});

test('a non-searchable metafield does not leak into search', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Mystery Item', 'ms' => 'Mystery Item']]);
    // storage_instructions is searchable=false in the registry.
    $product->metafields()->create(['key' => 'storage_instructions', 'value' => 'zephyrqx']);

    expect(Product::search('zephyrqx')->keys()->all())->not->toContain($product->id);
});
