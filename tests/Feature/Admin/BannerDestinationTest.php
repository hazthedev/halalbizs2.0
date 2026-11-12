<?php

use App\Enums\StoreStatus;
use App\Livewire\Admin\Content\Banners;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\AppLinks;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

// CMS item 3: an admin picks where a banner goes from a list; every choice
// opens on the web AND in the app.

function bannerForm(): Testable
{
    test()->seed(RoleSeeder::class);
    Storage::fake('public');

    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    return Livewire::actingAs($admin)
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Raya Sale')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1200, 400));
}

function savedAppUrl(): ?string
{
    return test()->getJson('/api/v1/home')->json('data.banners.0.app_url');
}

test('a picked product becomes the banner link on web and app', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Ajwa Dates 500g']]);
    Product::factory()->create(['name' => ['en' => 'Sambal Paste']]);

    $form = bannerForm()
        ->set('destination', 'product')
        ->set('destinationQuery', 'ajwa');

    expect($form->instance()->destinationMatches())->toBe([$product->slug => 'Ajwa Dates 500g']);

    $form->call('chooseDestination', $product->slug)->call('save')->assertHasNoErrors()
        ->call('publishDraft', 'banner', Banner::first()->id);

    expect(Banner::first()->link_url)->toBe('/p/'.$product->slug)
        ->and(savedAppUrl())->toBe('/p/'.$product->slug);
});

test('a product that is not live or does not exist is refused', function () {
    bannerForm()
        ->set('destination', 'product')
        ->set('destinationSlug', 'no-such-product')
        ->call('save')
        ->assertHasErrors('destinationSlug');

    expect(Banner::count())->toBe(0);
});

test('store, category and page destinations save their own links', function (string $type, Closure $make, string $prefix) {
    $slug = $make();

    bannerForm()->set('destination', $type)->set('destinationSlug', $slug)->call('save')->assertHasNoErrors()
        ->call('publishDraft', 'banner', Banner::first()->id);

    expect(Banner::first()->link_url)->toBe($prefix.$slug)
        ->and(savedAppUrl())->toBe($prefix.$slug);
})->with([
    'store' => ['store', fn () => Store::factory()->create(['status' => StoreStatus::Approved])->slug, '/s/'],
    'category' => ['category', fn () => Category::factory()->create(['is_active' => true])->slug, '/c/'],
    'page' => ['page', fn () => Page::create(['slug' => 'terms', 'title' => ['en' => 'Terms'], 'body' => ['en' => '<p>t</p>'], 'is_active' => true])->slug, '/page/'],
]);

test('flash sale, help and search destinations need no record', function (string $type, string $query, string $link) {
    bannerForm()->set('destination', $type)->set('destinationQuery', $query)->call('save')->assertHasNoErrors()
        ->call('publishDraft', 'banner', Banner::first()->id);

    expect(Banner::first()->link_url)->toBe($link)
        ->and(savedAppUrl())->toBe($link);
})->with([
    ['flash_sale', '', '/flash-sale'],
    ['help', '', '/help'],
    ['search', 'kurma ajwa', '/search?q=kurma%20ajwa'],
]);

test('search needs words to search for', function () {
    bannerForm()->set('destination', 'search')->set('destinationQuery', '  ')->call('save')->assertHasErrors('destinationQuery');
});

test('no link saves no link, even when an address was typed earlier', function () {
    bannerForm()->set('linkUrl', '/c/snacks')->set('destination', 'none')->call('save')->assertHasNoErrors();

    expect(Banner::first()->link_url)->toBeNull();
});

test('a web address still works, but never into checkout or payment', function () {
    bannerForm()->set('destination', 'custom')->set('linkUrl', 'https://example.com/raya')->call('save')->assertHasNoErrors();

    expect(Banner::first()->link_url)->toBe('https://example.com/raya');

    foreach (['/checkout', '/pay/HB-1', '/payments/processing/HB-1', '/app/pay/HB-1', 'https://halalbizs.com/checkout/success/HB-1'] as $blocked) {
        bannerForm()->set('destination', 'custom')->set('linkUrl', $blocked)->call('save')->assertHasErrors('linkUrl');
    }
});

test('editing reads the saved link back into the picker', function (string $link, string $type, string $slug, string $query, string $custom) {
    $product = Product::factory()->create(['slug' => 'ajwa', 'name' => ['en' => 'Ajwa Dates']]);
    Category::factory()->create(['slug' => 'snacks', 'is_active' => true]);
    $banner = Banner::create(['title' => ['en' => 'B'], 'link_url' => $link, 'position' => 1, 'is_active' => true]);

    bannerForm()
        ->call('edit', $banner->id)
        ->assertSet('destination', $type)
        ->assertSet('destinationSlug', $slug)
        ->assertSet('destinationQuery', $query)
        ->assertSet('linkUrl', $custom);
})->with([
    ['/p/ajwa', 'product', 'ajwa', 'Ajwa Dates', ''],
    ['/c/snacks', 'category', 'snacks', '', ''],
    ['/flash-sale', 'flash_sale', '', '', ''],
    ['/search?q=kurma%20ajwa', 'search', '', 'kurma ajwa', ''],
    ['https://example.com/raya', 'custom', '', '', 'https://example.com/raya'],
    ['/p/deleted-product', 'custom', '', '', '/p/deleted-product'],
]);

test('a banner with no link opens the picker on No link', function () {
    $banner = Banner::create(['title' => ['en' => 'B'], 'link_url' => null, 'position' => 1, 'is_active' => true]);

    bannerForm()->call('edit', $banner->id)->assertSet('destination', 'none');
});

test('AppLinks opens every banner destination in the app', function (?string $web, ?string $app) {
    expect(AppLinks::fromWebUrl($web))->toBe($app);
})->with([
    ['/page/terms', '/page/terms'],
    ['/flash-sale', '/flash-sale'],
    ['/help', '/help'],
    ['/help/article/12', '/help/12'],
    ['/live/raya-live', '/live/raya-live'],
    ['/search?q=kurma%20ajwa', '/search?q=kurma%20ajwa'],
    ['https://halalbizs.com/search?q=dates&sort=new', '/search?q=dates'],
    ['/search', '/search'],
    ['/checkout', null],
    ['https://example.com/raya', null],
]);
