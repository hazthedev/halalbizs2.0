<?php

use App\Livewire\Admin\Content\Banners\Form as BannersForm;
use App\Livewire\Admin\Content\FlashSales;
use App\Livewire\Admin\Content\Pages\Form as PagesForm;
use App\Models\Banner;
use App\Models\FlashSale;
use App\Models\Page;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(contentPublisher());
    Storage::fake('public');
});

test('the admin menu links to flash sales', function () {
    $this->get(route('admin.content.banners'))->assertOk()->assertSee(route('admin.content.flash-sales'), false);
});

test('flash sales uses the standard heading and table, with an empty state', function () {
    Livewire::test(FlashSales::class)->assertDontSee('max-w-5xl', false)->assertSee(__('No flash sales yet.'));

    FlashSale::create(['title' => 'Mega', 'starts_at' => now(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(FlashSales::class)->assertSeeHtml('scope="col"')->assertSeeHtml('wire:click="sortBy')->assertSee('Mega')
        ->call('sortBy', 'title')->assertSet('sort', 'title');
});

test('banner edit shows the current main image', function () {
    $banner = Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true]);
    $banner->addMedia(UploadedFile::fake()->image('cur.jpg', 1500, 500))->toMediaCollection('image');

    Livewire::test(BannersForm::class, ['banner' => $banner])->assertSeeHtml('alt="'.__('Current banner image').'"');
});

test('banner and page language fields sit inside their language tab', function () {
    $html = Livewire::test(BannersForm::class)->html();
    foreach (['en', 'ms', 'vi'] as $code) {
        expect($html)->toMatch("/x-show=\"tab === '$code'\"[^>]*>(?:(?!x-show=\"tab).)*subtitle\.$code(?:(?!x-show=\"tab).)*ctaLabel\.$code/s");
    }

    $html = Livewire::test(PagesForm::class)->html();
    foreach (['en', 'ms', 'vi'] as $code) {
        expect($html)->toMatch("/x-show=\"tab === '$code'\"[^>]*>(?:(?!x-show=\"tab).)*seoTitle\.$code(?:(?!x-show=\"tab).)*seoDescription\.$code/s");
    }
});

test('every language still saves from a banner and a page', function () {
    $lang = fn (string $v) => ['en' => "$v en", 'ms' => "$v ms", 'vi' => "$v vi"];

    Livewire::test(BannersForm::class)
        ->set('title', $lang('T'))->set('subtitle', $lang('S'))->set('ctaLabel', $lang('C'))
        ->set('destination', 'none')->set('image', UploadedFile::fake()->image('b.jpg', 1500, 500))
        ->call('save')->assertHasNoErrors();
    $p = Banner::sole()->draft->payload;
    expect(json_decode($p['subtitle'], true))->toBe($lang('S'))->and(json_decode($p['cta_label'], true))->toBe($lang('C'));

    Livewire::test(PagesForm::class)
        ->set('slug', 'cleanup')->set('title', $lang('T'))->set('body', $lang('B'))
        ->set('seoTitle', $lang('ST'))->set('seoDescription', $lang('SD'))
        ->call('save')->assertHasNoErrors();
    $p = Page::where('slug', 'cleanup')->sole()->draft->payload;
    expect(json_decode($p['seo_title'], true))->toBe($lang('ST'))->and(json_decode($p['seo_description'], true))->toBe($lang('SD'));
});

test('theme file input matches the form file inputs', function () {
    $html = Livewire::test(\App\Livewire\Admin\Content\Theme::class)->html();
    expect($html)->toContain('border-line-strong bg-surface px-3.5 py-2.5');
});
