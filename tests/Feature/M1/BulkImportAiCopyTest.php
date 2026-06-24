<?php

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Livewire\Seller\Products\BulkImport;
use App\Livewire\Seller\Products\Form;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ListingCopyService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['services.anthropic.key' => null]); // force the deterministic template path
});

function importSeller(): User
{
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    return $seller;
}

test('the listing copy service falls back to bilingual template copy without an API key', function () {
    $copy = app(ListingCopyService::class)->generate('Cotton Tee', ['Red', 'Large']);

    expect($copy['en'])->toContain('Cotton Tee')
        ->and($copy['en'])->toContain('Red')
        ->and($copy['ms'])->not->toBe('');
});

test('the product form fills a draft description with AI', function () {
    Livewire::actingAs(importSeller())
        ->test(Form::class)
        ->set('name.en', 'Cotton Tee')
        ->call('generateCopy')
        ->assertSet('description.en', fn ($value) => str_contains((string) $value, 'Cotton Tee'))
        ->assertSet('description.ms', fn ($value) => trim((string) $value) !== '');
});

test('bulk import creates draft products and reports row errors', function () {
    $seller = importSeller();
    $store = $seller->store;
    $category = Category::factory()->create();

    $csv = "name_en,name_ms,description_en,category_id,price_rm,stock,sku\n"
        ."Cotton Tee,Baju Kapas,Soft tee,{$category->id},39.90,100,TEE-001\n"
        ."Bad Row,,,{$category->id},,5,\n"; // missing price → error

    Livewire::actingAs($seller)
        ->test(BulkImport::class)
        ->set('csv', UploadedFile::fake()->createWithContent('products.csv', $csv))
        ->call('import')
        ->assertSet('result.created', 1)
        ->assertSet('result.errors', fn ($errors) => count($errors) === 1);

    $product = Product::where('store_id', $store->id)->where('status', ProductStatus::Draft)->first();

    expect($product)->not->toBeNull()
        ->and($product->getTranslation('name', 'en'))->toBe('Cotton Tee')
        ->and($product->variants->first()->price_sen)->toBe(3990)
        ->and($product->variants->first()->stock)->toBe(100);
});
