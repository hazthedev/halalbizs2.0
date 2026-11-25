<?php

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Livewire\Seller\Products\BulkImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function importSeller(): User
{
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    return $seller;
}

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

test('bulk import accepts Vietnamese columns while old CSV headers remain compatible', function () {
    $seller = importSeller();
    $category = Category::factory()->create();
    $csv = "name_en,name_vi,description_en,description_vi,category_id,price_rm,stock,sku\n"
        ."Rice Crackers,Bánh gạo giòn,Crisp rice snack.,Món ăn nhẹ từ gạo giòn.,{$category->id},8.90,20,RICE-VI\n";

    Livewire::actingAs($seller)
        ->test(BulkImport::class)
        ->set('csv', UploadedFile::fake()->createWithContent('products-vi.csv', $csv))
        ->call('import')
        ->assertSet('result.created', 1);

    $product = Product::whereJsonContains('name->vi', 'Bánh gạo giòn')->sole();

    expect($product->getTranslation('description', 'vi', false))->toBe('Món ăn nhẹ từ gạo giòn.');
});
