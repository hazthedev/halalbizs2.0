<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Settings\ModerationSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

function sellerApiProductPayload(array $overrides = []): array
{
    return $overrides + [
        'name' => ['en' => 'Sambal Nyet', 'ms' => 'Sambal Nyet Pedas'],
        'description' => ['en' => '<p>Hot <em onmouseover="x()">sambal</em><script>alert(1)</script></p>'],
        'category_id' => Category::factory()->create()->id,
        'price_sen' => 1990,
        'sale_price_sen' => 1590,
        'stock' => 12,
        'sku' => 'SMB-001',
        'weight_grams' => 250,
        'cod_enabled' => false,
    ];
}

/** A product with one image so publish passes the image rule. */
function sellerApiProductWithImage(Product $product): Product
{
    $product->addMedia(UploadedFile::fake()->image('a.jpg', 300, 300))->toMediaCollection('images');

    return $product;
}

test('POST seller/products creates a sanitised single-variant draft', function () {
    $seller = sellerApiUser();

    $response = sellerApiAs($seller)->postJson('/api/v1/seller/products', sellerApiProductPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.name', 'Sambal Nyet')
        ->assertJsonPath('data.name_translations.ms', 'Sambal Nyet Pedas')
        ->assertJsonPath('data.name_translations.vi', '')
        ->assertJsonPath('data.price_sen', 1990)
        ->assertJsonPath('data.sale_price_sen', 1590)
        ->assertJsonPath('data.min_price_sen', 1590)
        ->assertJsonPath('data.stock_total', 12)
        ->assertJsonPath('data.variants_count', 1)
        ->assertJsonPath('data.variants.0.sku', 'SMB-001')
        ->assertJsonPath('data.variants.0.is_default', true)
        ->assertJsonPath('data.cod_enabled', false)
        ->assertJsonPath('data.weight_grams', 250)
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.blocked_reason', null);

    $product = Product::find($response->json('data.id'));
    expect($product->store_id)->toBe($seller->store->id)
        ->and($product->slug)->toBe('sambal-nyet')
        ->and($product->getTranslation('description', 'en'))->toBe('<p>Hot <em>sambal</em></p>')
        ->and($product->variants()->count())->toBe(1);
});

test('product validation: English name required, non-leaf category and sale ≥ price are 422', function () {
    $seller = sellerApiUser();

    sellerApiAs($seller)->postJson('/api/v1/seller/products', sellerApiProductPayload(['name' => ['ms' => 'Tanpa Inggeris']]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['name.en']]]);

    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    sellerApiAs($seller)->postJson('/api/v1/seller/products', sellerApiProductPayload(['category_id' => $parent->id]))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.category_id.0', __('Pick the most specific category — this one has sub-categories.'));

    sellerApiAs($seller)->postJson('/api/v1/seller/products', sellerApiProductPayload(['sale_price_sen' => 1990]))
        ->assertStatus(422)
        ->assertJsonPath('error.fields.sale_price_sen.0', __('The sale price must be below the normal price.'));

    expect($seller->store->products()->count())->toBe(0);
});

test('GET seller/products lists this store only with status and keyword filters', function () {
    $seller = sellerApiUser();
    $live = Product::factory()->create(['store_id' => $seller->store->id, 'name' => ['en' => 'Kurma Ajwa']]);
    $draft = Product::factory()->create(['store_id' => $seller->store->id, 'status' => ProductStatus::Draft, 'name' => ['en' => 'Madu Tualang']]);
    Product::factory()->create(); // another store

    sellerApiAs($seller)->getJson('/api/v1/seller/products')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'name_translations', 'status', 'status_label', 'category', 'thumb', 'price_sen', 'sale_price_sen', 'min_price_sen', 'stock_total', 'variants_count', 'cod_enabled', 'updated_at']]]);

    sellerApiAs($seller)->getJson('/api/v1/seller/products?status=draft')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $draft->id);

    sellerApiAs($seller)->getJson('/api/v1/seller/products?q=kurma')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $live->id);

    sellerApiAs($seller)->getJson("/api/v1/seller/products/{$live->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $live->id)
        ->assertJsonStructure(['data' => ['description', 'description_translations', 'variants', 'images', 'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'sale_starts_at', 'sale_ends_at', 'blocked_reason']]);
});

test('PATCH seller/products updates basics and variant price/stock, writing a stock movement', function () {
    $seller = sellerApiUser();
    $product = Product::factory()->create(['store_id' => $seller->store->id, 'status' => ProductStatus::Draft]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 10]);

    sellerApiAs($seller)->patchJson("/api/v1/seller/products/{$product->id}", [
        'name' => ['en' => 'Renamed', 'vi' => 'Đổi tên'],
        'cod_enabled' => true,
        'variants' => [['id' => $variant->id, 'price_sen' => 6000, 'sale_price_sen' => 4500, 'stock' => 4, 'sku' => 'NEW-1']],
    ])
        ->assertOk()
        ->assertJsonPath('data.name_translations.en', 'Renamed')
        ->assertJsonPath('data.name_translations.vi', 'Đổi tên')
        ->assertJsonPath('data.cod_enabled', true)
        ->assertJsonPath('data.variants.0.price_sen', 6000)
        ->assertJsonPath('data.variants.0.sale_price_sen', 4500)
        ->assertJsonPath('data.variants.0.stock', 4)
        ->assertJsonPath('data.variants.0.sku', 'NEW-1');

    $movement = StockMovement::where('product_variant_id', $variant->id)->latest('id')->first();
    expect($movement->qty_delta)->toBe(-6)
        ->and($movement->balance_after)->toBe(4)
        ->and($movement->reference)->toBe('seller-api');

    // Top-level price/stock address the default variant.
    sellerApiAs($seller)->patchJson("/api/v1/seller/products/{$product->id}", ['price_sen' => 7000, 'sale_price_sen' => null, 'stock' => 9])
        ->assertOk()
        ->assertJsonPath('data.price_sen', 7000)
        ->assertJsonPath('data.sale_price_sen', null)
        ->assertJsonPath('data.stock_total', 9);

    expect(StockMovement::where('product_variant_id', $variant->id)->count())->toBe(2);

    sellerApiAs($seller)->patchJson("/api/v1/seller/products/{$product->id}", [
        'variants' => [['id' => $variant->id, 'sale_price_sen' => 7000]],
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['variants.0.sale_price_sen']]]);
});

test("PATCH with another product's variant id or another store's product is a 404", function () {
    $seller = sellerApiUser();
    $own = Product::factory()->create(['store_id' => $seller->store->id]);
    $foreign = Product::factory()->create();
    $foreignVariant = $foreign->variants->first();

    sellerApiAs($seller)->patchJson("/api/v1/seller/products/{$own->id}", [
        'variants' => [['id' => $foreignVariant->id, 'stock' => 0]],
    ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    sellerApiAs($seller)->patchJson("/api/v1/seller/products/{$foreign->id}", ['name' => ['en' => 'Hijacked']])
        ->assertNotFound();

    sellerApiAs($seller)->getJson("/api/v1/seller/products/{$foreign->id}")->assertNotFound();

    expect($foreignVariant->fresh()->stock)->toBe($foreignVariant->stock)
        ->and($foreign->fresh()->getTranslation('name', 'en'))->not->toBe('Hijacked');
});

test('images upload appends up to nine, then can be deleted', function () {
    Storage::fake('public');

    $seller = sellerApiUser();
    $product = Product::factory()->create(['store_id' => $seller->store->id, 'status' => ProductStatus::Draft]);

    $response = sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg', 300, 300), UploadedFile::fake()->image('b.jpg', 300, 300)],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.images');

    expect($response->json('data.thumb'))->not->toBeNull()
        ->and($response->json('data.images.0'))->toHaveKeys(['id', 'url', 'thumb']);

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/images", ['images' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['images.0']]]);

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/images", [
        'images' => collect(range(1, 8))->map(fn ($i) => UploadedFile::fake()->image("x$i.jpg", 100, 100))->all(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.images.0', __('A product can have up to :max images — remove :excess.', ['max' => 9, 'excess' => 1]));

    expect($product->fresh()->getMedia('images'))->toHaveCount(2);

    $mediaId = $response->json('data.images.0.id');

    sellerApiAs($seller)->deleteJson("/api/v1/seller/products/{$product->id}/images/{$mediaId}")->assertOk();
    sellerApiAs($seller)->deleteJson("/api/v1/seller/products/{$product->id}/images/{$mediaId}")->assertNotFound();

    expect($product->fresh()->getMedia('images'))->toHaveCount(1);
});

test('publish needs an image, then goes live (or pending review under moderation); delist and relist move the status', function () {
    Storage::fake('public');

    $seller = sellerApiUser();
    $product = Product::factory()->create(['store_id' => $seller->store->id, 'status' => ProductStatus::Draft, 'published_at' => null]);

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/publish")
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['images']]]);

    sellerApiProductWithImage($product);

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'live');

    expect($product->fresh()->published_at)->not->toBeNull();

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/relist")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/delist")
        ->assertOk()
        ->assertJsonPath('data.status', 'delisted');

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/delist")->assertStatus(409);

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/relist")
        ->assertOk()
        ->assertJsonPath('data.status', 'live');

    $settings = app(ModerationSettings::class);
    $settings->require_product_approval = true;
    $settings->save();

    $draft = sellerApiProductWithImage(Product::factory()->create(['store_id' => $seller->store->id, 'status' => ProductStatus::Draft]));

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$draft->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');
});

test('publish is a 409 halal_blocked when the category needs a certificate the product lacks', function () {
    Storage::fake('public');
    config(['halal.gate_enforced_from' => now()->subDay()->toDateString()]);

    $seller = sellerApiUser();
    $category = Category::factory()->create(['requires_halal_certificate' => true]);
    $product = sellerApiProductWithImage(Product::factory()->create([
        'store_id' => $seller->store->id, 'status' => ProductStatus::Draft, 'category_id' => $category->id,
    ]));

    sellerApiAs($seller)->getJson("/api/v1/seller/products/{$product->id}")
        ->assertJsonPath('data.blocked_reason', __('This category needs a halal certificate. Register yours under Halal certificates, then tick this product.'));

    sellerApiAs($seller)->postJson("/api/v1/seller/products/{$product->id}/publish")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'halal_blocked');

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});
