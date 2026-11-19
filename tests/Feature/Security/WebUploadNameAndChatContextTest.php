<?php

use App\Enums\ProductStatus;
use App\Livewire\Seller\Apply;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Seller\Settings;
use App\Livewire\Storefront\Account\Messages;
use App\Models\Category;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * HB-G123: web seller uploads are stored under a server-generated name with
 * an extension from the detected mime type, never the client's file name
 * (same rule as the app API, PR #225 / UploadName).
 * HB-G121: web buyer chat only attaches a LIVE product, as the app API does.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
});

function g123Seller(): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/** Generated name: optional prefix, 40 random chars, detected extension. */
function expectGeneratedName(string $fileName, string $prefix, string $extension): void
{
    expect($fileName)->toMatch('/^'.preg_quote($prefix, '/').'[A-Za-z0-9]{40}\.'.$extension.'$/')
        ->and($fileName)->not->toContain('evil')
        ->and($fileName)->not->toContain('my');
}

test('product form images and video get generated names with the detected extension', function () {
    $seller = g123Seller();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Dates')
        ->set('categoryTop', Category::factory()->create()->id)
        ->set('price', '19.90')
        ->set('stock', '5')
        ->set('newImages', [
            UploadedFile::fake()->image('evil.php.jpg', 600, 600),
            UploadedFile::fake()->image('my file.PNG', 600, 600),
        ])
        ->set('newVideo', UploadedFile::fake()->create('evil.php.mp4', 1024, 'video/mp4'))
        ->call('publish')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();
    $images = $product->getMedia('images')->pluck('file_name')->all();

    expect($images)->toHaveCount(2);
    expectGeneratedName($images[0], '', 'jpg');
    expectGeneratedName($images[1], '', 'png');
    expectGeneratedName($product->getFirstMedia('videos')->file_name, '', 'mp4');
    expect($product->getFirstMedia('images')->disk)->toBe('public');
});

test('shop settings logo and banner get generated names with the detected extension', function () {
    $seller = g123Seller();

    Livewire::actingAs($seller)
        ->test(Settings::class)
        ->set('description', 'Fresh dates from Madinah.')
        ->set('state', 'Selangor')
        ->set('logo', UploadedFile::fake()->image('evil.php.jpg'))
        ->set('banner', UploadedFile::fake()->image('my file.PNG'))
        ->call('saveProfile')
        ->assertHasNoErrors();

    $store = $seller->store->fresh();

    expectGeneratedName($store->getFirstMedia('logo')->file_name, 'logo-', 'jpg');
    expectGeneratedName($store->getFirstMedia('banner')->file_name, 'banner-', 'png');
});

test('seller apply documents get generated names with the detected extension', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('buyer');

    Livewire::actingAs($user)
        ->test(Apply::class)
        ->set('name', 'Kedai Pak Ali')
        ->set('description', 'Homemade sambal and kuih, made fresh daily in Shah Alam.')
        ->set('state', 'Selangor')
        ->set('bankName', 'Maybank')
        ->set('accountName', 'Ali bin Abu')
        ->set('accountNumber', '1234567890')
        ->set('ssmFile', UploadedFile::fake()->create('evil.php.pdf', 200, 'application/pdf'))
        ->set('icFile', UploadedFile::fake()->image('my file.PNG'))
        ->set('confirm', true)
        ->call('submit')
        ->assertHasNoErrors();

    $documents = Store::where('user_id', $user->id)->firstOrFail()->documents->keyBy('type');

    expectGeneratedName($documents['ssm']->getFirstMedia('file')->file_name, 'ssm-', 'pdf');
    expectGeneratedName($documents['ic']->getFirstMedia('file')->file_name, 'ic-', 'png');
    expect($documents['ic']->getFirstMedia('file')->disk)->toBe('local');
});

test('web chat drops a non-live product from the message, as the app API does, and keeps a live one', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $store = g123Seller()->store;
    $conversation = app(ChatService::class)->openConversation($buyer, $store);

    $draft = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Draft]);
    $live = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Live]);

    $component = Livewire::actingAs($buyer)
        ->test(Messages::class)
        ->call('openConversation', $conversation->id)
        ->set('contextProductId', $draft->id)
        ->set('body', 'About the draft')
        ->call('send')
        ->assertHasNoErrors();

    $component->set('contextProductId', $live->id)
        ->set('body', 'About the live one')
        ->call('send')
        ->assertHasNoErrors();

    expect(Message::where('body', 'About the draft')->sole()->product_id)->toBeNull()
        ->and(Message::where('body', 'About the live one')->sole()->product_id)->toBe($live->id);
});
