<?php

use App\Enums\ProductStatus;
use App\Livewire\Seller\Products\Index;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Settings\ModerationSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function productIndexSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

function productIndexOwnProduct(User $seller, array $attributes = []): Product
{
    return Product::factory()->create(['store_id' => $seller->store->id, ...$attributes]);
}

test('index lists only the current store\'s products', function () {
    $seller = productIndexSeller();

    productIndexOwnProduct($seller, ['name' => ['en' => 'My Own Honey Jar']]);
    Product::factory()->create(['name' => ['en' => 'Foreign Store Gadget']]);

    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertSee('My Own Honey Jar')
        ->assertDontSee('Foreign Store Gadget');
});

test('status filter narrows the list', function () {
    $seller = productIndexSeller();

    productIndexOwnProduct($seller, ['name' => ['en' => 'Visible Draft Kettle'], 'status' => ProductStatus::Draft]);
    productIndexOwnProduct($seller, ['name' => ['en' => 'Visible Live Teapot'], 'status' => ProductStatus::Live]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->set('status', 'draft')
        ->assertSee('Visible Draft Kettle')
        ->assertDontSee('Visible Live Teapot');
});

test('low stock filter keeps only products with a variant under the threshold', function () {
    $seller = productIndexSeller();

    $low = productIndexOwnProduct($seller, ['name' => ['en' => 'Nearly Gone Dates']]);
    $low->variants()->update(['stock' => 2]);

    $full = productIndexOwnProduct($seller, ['name' => ['en' => 'Fully Stocked Prunes']]);
    $full->variants()->update(['stock' => 100]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->set('lowStock', true)
        ->assertSee('Nearly Gone Dates')
        ->assertDontSee('Fully Stocked Prunes');
});

test('search matches variant SKU', function () {
    $seller = productIndexSeller();

    $match = productIndexOwnProduct($seller, ['name' => ['en' => 'Plain Cotton Scarf']]);
    $match->variants()->update(['sku' => 'SCARF-XYZ-1']);

    $other = productIndexOwnProduct($seller, ['name' => ['en' => 'Leather Belt Classic']]);
    $other->variants()->update(['sku' => 'BELT-ABC-1']);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->set('search', 'SCARF-XYZ')
        ->assertSee('Plain Cotton Scarf')
        ->assertDontSee('Leather Belt Classic');
});

test('duplicate clones options, values and variants as a draft with remapped value ids', function () {
    $seller = productIndexSeller();

    $product = Product::factory()
        ->withVariants(colour: 3, size: 2)
        ->create(['store_id' => $seller->store->id, 'name' => ['en' => 'Matrix Jacket']]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('duplicate', $product->id);

    $copy = Product::query()
        ->where('store_id', $seller->store->id)
        ->where('id', '!=', $product->id)
        ->firstOrFail();

    expect($copy->getTranslation('name', 'en'))->toBe('Matrix Jacket (copy)')
        ->and($copy->status)->toBe(ProductStatus::Draft)
        ->and($copy->slug)->not->toBe($product->slug)
        ->and($copy->options()->count())->toBe(2)
        ->and($copy->variants()->count())->toBe(6)
        ->and($copy->getMedia('images'))->toHaveCount(0);

    // Every copied variant must reference the COPY's option values, not the original's.
    $copyValueIds = $copy->options()->with('values')->get()
        ->flatMap(fn ($option) => $option->values->pluck('id'))
        ->all();

    $originalValueIds = $product->options()->with('values')->get()
        ->flatMap(fn ($option) => $option->values->pluck('id'))
        ->all();

    foreach ($copy->variants as $variant) {
        foreach ($variant->option_value_ids as $valueId) {
            expect($copyValueIds)->toContain($valueId)
                ->and($originalValueIds)->not->toContain($valueId);
        }
    }
});

test('delist moves a live product to delisted and relist brings it back live', function () {
    $seller = productIndexSeller();
    $product = productIndexOwnProduct($seller, ['status' => ProductStatus::Live]);

    $component = Livewire::actingAs($seller)->test(Index::class);

    $component->call('delist', $product->id);
    expect($product->refresh()->status)->toBe(ProductStatus::Delisted);

    $component->call('relist', $product->id);
    expect($product->refresh()->status)->toBe(ProductStatus::Live);
});

test('relist goes to pending review when product approval is required', function () {
    $seller = productIndexSeller();
    $product = productIndexOwnProduct($seller, ['status' => ProductStatus::Delisted]);

    $settings = app(ModerationSettings::class);
    $settings->require_product_approval = true;
    $settings->save();

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('relist', $product->id);

    expect($product->refresh()->status)->toBe(ProductStatus::PendingReview);
});

test('row actions cannot touch another store\'s product', function () {
    $seller = productIndexSeller();
    $foreign = Product::factory()->create(['status' => ProductStatus::Live]);

    expect(fn () => Livewire::actingAs($seller)->test(Index::class)->call('delist', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreign->refresh()->status)->toBe(ProductStatus::Live);
});

test('bulk delete removes selected drafts only', function () {
    $seller = productIndexSeller();

    $draft = productIndexOwnProduct($seller, ['status' => ProductStatus::Draft]);
    $live = productIndexOwnProduct($seller, ['status' => ProductStatus::Live]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->set('selected', [(string) $draft->id, (string) $live->id])
        ->call('bulkDelete');

    expect(Product::find($draft->id))->toBeNull()
        ->and(Product::withTrashed()->find($draft->id)->trashed())->toBeTrue()
        ->and(Product::find($live->id))->not->toBeNull();
});

test('bulk delist moves selected live products to delisted', function () {
    $seller = productIndexSeller();

    $live = productIndexOwnProduct($seller, ['status' => ProductStatus::Live]);
    $draft = productIndexOwnProduct($seller, ['status' => ProductStatus::Draft]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->set('selected', [(string) $live->id, (string) $draft->id])
        ->call('bulkDelist');

    expect($live->refresh()->status)->toBe(ProductStatus::Delisted)
        ->and($draft->refresh()->status)->toBe(ProductStatus::Draft);
});
