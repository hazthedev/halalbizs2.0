<?php

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Admin\Content\Banners;
use App\Livewire\Admin\Content\HomeSections;
use App\Livewire\Admin\Content\Pages;
use App\Livewire\Admin\Content\Vouchers;
use App\Livewire\Storefront\Home;
use App\Models\Banner;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\HomeSectionSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function contentAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

// ── Banners ─────────────────────────────────────────────────────────────

test('admin creates a banner with image, schedule, and translations', function () {
    Storage::fake('public');

    Livewire::actingAs(contentAdmin())
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Raya Sale')
        ->set('title.ms', 'Jualan Raya')
        ->set('linkUrl', '/c/snacks')
        ->set('startsAt', '2026-06-01T00:00')
        ->set('endsAt', '2026-06-30T23:59')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1200, 400))
        ->call('save')
        ->assertHasNoErrors();

    $banner = Banner::sole();

    expect($banner->getTranslation('title', 'en'))->toBe('Raya Sale')
        ->and($banner->getTranslation('title', 'ms'))->toBe('Jualan Raya')
        ->and($banner->link_url)->toBe('/c/snacks')
        ->and($banner->starts_at->format('Y-m-d H:i'))->toBe('2026-06-01 00:00')
        ->and($banner->ends_at->format('Y-m-d H:i'))->toBe('2026-06-30 23:59')
        ->and($banner->is_active)->toBeTrue()
        ->and($banner->getFirstMedia('image'))->not->toBeNull();
});

test('a banner video saves and renders as an autoplaying slide on the home page', function () {
    Storage::fake('public');

    Livewire::actingAs(contentAdmin())
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Moving Raya')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1200, 400))
        ->set('video', UploadedFile::fake()->create('promo.mp4', 2048, 'video/mp4'))
        ->call('save')
        ->assertHasNoErrors();

    $banner = Banner::sole();

    expect($banner->getFirstMedia('video'))->not->toBeNull();

    HomeSection::create(['type' => 'banner', 'title' => ['en' => 'Promos'], 'position' => 0, 'is_active' => true]);

    Livewire::test(Home::class)
        ->assertSee('autoplay muted loop playsinline', false)
        ->assertSee($banner->getFirstMediaUrl('video'), false);
});

test('a banner video must be MP4 or WebM', function () {
    Storage::fake('public');

    Livewire::actingAs(contentAdmin())
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Bad Video Banner')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1200, 400))
        ->set('video', UploadedFile::fake()->create('clip.avi', 1024, 'video/x-msvideo'))
        ->call('save')
        ->assertHasErrors(['video' => 'mimetypes']);

    expect(Banner::count())->toBe(0);
});

test('banner image is required on create but not on edit', function () {
    Storage::fake('public');
    $admin = contentAdmin();

    Livewire::actingAs($admin)
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'No image')
        ->call('save')
        ->assertHasErrors(['image' => 'required']);

    $banner = Banner::create(['title' => ['en' => 'Existing'], 'position' => 0]);

    Livewire::actingAs($admin)
        ->test(Banners::class)
        ->call('edit', $banner->id)
        ->set('title.en', 'Existing — renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($banner->refresh()->getTranslation('title', 'en'))->toBe('Existing — renamed');
});

test('banner schedule must end after it starts', function () {
    Storage::fake('public');

    Livewire::actingAs(contentAdmin())
        ->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Backwards window')
        ->set('startsAt', '2026-06-30T00:00')
        ->set('endsAt', '2026-06-01T00:00')
        ->set('image', UploadedFile::fake()->image('banner.jpg'))
        ->call('save')
        ->assertHasErrors(['endsAt']);
});

test('admin toggles, reorders, and deletes banners', function () {
    $admin = contentAdmin();
    $first = Banner::create(['title' => ['en' => 'First'], 'position' => 0]);
    $second = Banner::create(['title' => ['en' => 'Second'], 'position' => 1]);

    Livewire::actingAs($admin)
        ->test(Banners::class)
        ->call('toggleActive', $first->id)
        ->call('move', $second->id, -1);

    expect($first->refresh()->is_active)->toBeFalse()
        ->and($second->refresh()->position)->toBe(0)
        ->and($first->position)->toBe(1);

    Livewire::actingAs($admin)
        ->test(Banners::class)
        ->call('delete', $first->id);

    expect(Banner::find($first->id))->toBeNull()
        ->and(Banner::count())->toBe(1);
});

// ── Home sections ───────────────────────────────────────────────────────

test('home section payload saves with the storefront contract keys and home still renders', function () {
    test()->seed(HomeSectionSeeder::class);
    $section = HomeSection::where('type', 'product_carousel')->sole();

    Livewire::actingAs(contentAdmin())
        ->test(HomeSections::class)
        ->call('edit', $section->id)
        ->set('title.en', 'Hot right now')
        ->set('title.ms', 'Hangat sekarang')
        ->set('source', 'top')
        ->set('limit', '6')
        ->call('save')
        ->assertHasNoErrors();

    $section->refresh();

    expect($section->payload)->toBe(['source' => 'top', 'limit' => 6])
        ->and($section->getTranslation('title', 'en'))->toBe('Hot right now')
        ->and($section->getTranslation('title', 'ms'))->toBe('Hangat sekarang');

    // The storefront home page (B1) must keep rendering after edits.
    test()->get('/')->assertOk();
});

test('category grid payload keeps only the limit key', function () {
    test()->seed(HomeSectionSeeder::class);
    $section = HomeSection::where('type', 'category_grid')->sole();

    Livewire::actingAs(contentAdmin())
        ->test(HomeSections::class)
        ->call('edit', $section->id)
        ->set('limit', '4')
        ->call('save')
        ->assertHasNoErrors();

    expect($section->refresh()->payload)->toBe(['limit' => 4]);

    test()->get('/')->assertOk();
});

test('only missing section types can be added, and sections reorder and delete', function () {
    $admin = contentAdmin();
    HomeSection::create(['type' => 'banner', 'position' => 0, 'is_active' => true]);

    // banner already exists — adding it again is a no-op.
    Livewire::actingAs($admin)
        ->test(HomeSections::class)
        ->set('addType', 'banner')
        ->call('addSection');

    expect(HomeSection::where('type', 'banner')->count())->toBe(1);

    // a missing type is created with the default payload contract.
    Livewire::actingAs($admin)
        ->test(HomeSections::class)
        ->set('addType', 'product_grid')
        ->call('addSection');

    $grid = HomeSection::where('type', 'product_grid')->sole();
    expect($grid->payload)->toBe(['source' => 'top', 'limit' => 12]);

    Livewire::actingAs($admin)
        ->test(HomeSections::class)
        ->call('move', $grid->id, -1)
        ->call('toggleActive', $grid->id);

    expect($grid->refresh()->position)->toBe(0)
        ->and($grid->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(HomeSections::class)
        ->call('delete', $grid->id);

    expect(HomeSection::find($grid->id))->toBeNull();
});

// ── Pages ───────────────────────────────────────────────────────────────

test('system page slug is locked and the page cannot be deleted', function () {
    test()->seed(PageSeeder::class);
    $terms = Page::where('slug', 'terms')->sole();

    Livewire::actingAs(contentAdmin())
        ->test(Pages::class)
        ->call('edit', $terms->id)
        ->set('slug', 'hijacked-slug')
        ->set('title.en', 'Terms & Conditions v2')
        ->set('body.en', '<p>Updated terms.</p>')
        ->call('save')
        ->assertHasNoErrors()
        ->call('delete', $terms->id);

    $terms->refresh();

    expect($terms->slug)->toBe('terms')
        ->and($terms->getTranslation('title', 'en'))->toBe('Terms & Conditions v2')
        ->and(Page::where('slug', 'terms')->exists())->toBeTrue();
});

test('terms and privacy cannot be deactivated but other system pages can', function () {
    test()->seed(PageSeeder::class);
    $admin = contentAdmin();
    $terms = Page::where('slug', 'terms')->sole();
    $about = Page::where('slug', 'about')->sole();

    Livewire::actingAs($admin)
        ->test(Pages::class)
        ->call('toggleActive', $terms->id)
        ->call('toggleActive', $about->id);

    expect($terms->refresh()->is_active)->toBeTrue()
        ->and($about->refresh()->is_active)->toBeFalse();
});

test('page body is sanitized to the allowed tag list on save', function () {
    Livewire::actingAs(contentAdmin())
        ->test(Pages::class)
        ->call('create')
        ->set('slug', 'shipping-guide')
        ->set('title.en', 'Shipping guide')
        ->set('body.en', '<script>alert(1)</script><p>Ships in <strong>2 days</strong>.</p><iframe src="x"></iframe><h2>Zones</h2>')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::where('slug', 'shipping-guide')->sole();

    expect($page->getTranslation('body', 'en'))
        ->toBe('alert(1)<p>Ships in <strong>2 days</strong>.</p><h2>Zones</h2>');
});

// ── Platform vouchers ───────────────────────────────────────────────────

test('platform voucher is created with RM-to-sen conversion and uppercase code', function () {
    Livewire::actingAs(contentAdmin())
        ->test(Vouchers::class)
        ->call('create')
        ->set('code', 'merdeka5')
        ->set('type', 'fixed')
        ->set('value', '5.00')
        ->set('minSpend', '50.00')
        ->set('quota', '100')
        ->set('perUserLimit', '2')
        ->set('startsAt', '2026-06-01T00:00')
        ->set('endsAt', '2026-12-31T23:59')
        ->call('save')
        ->assertHasNoErrors();

    $voucher = Voucher::sole();

    expect($voucher->scope)->toBe(VoucherScope::Platform)
        ->and($voucher->store_id)->toBeNull()
        ->and($voucher->code)->toBe('MERDEKA5')
        ->and($voucher->type)->toBe(VoucherType::Fixed)
        ->and($voucher->value_sen)->toBe(500)
        ->and($voucher->min_spend_sen)->toBe(5000)
        ->and($voucher->quota)->toBe(100)
        ->and($voucher->per_user_limit)->toBe(2);
});

test('percent voucher stores percent and the RM cap in sen', function () {
    Livewire::actingAs(contentAdmin())
        ->test(Vouchers::class)
        ->call('create')
        ->set('code', 'JIMAT10')
        ->set('type', 'percent')
        ->set('percent', '10')
        ->set('maxDiscount', '20.00')
        ->set('startsAt', '2026-06-01T00:00')
        ->set('endsAt', '2026-12-31T23:59')
        ->call('save')
        ->assertHasNoErrors();

    $voucher = Voucher::sole();

    expect($voucher->type)->toBe(VoucherType::Percent)
        ->and((string) $voucher->percent)->toBe('10.00')
        ->and($voucher->max_discount_sen)->toBe(2000)
        ->and($voucher->value_sen)->toBeNull();
});

test('platform voucher codes must be unique among platform vouchers', function () {
    $admin = contentAdmin();

    Voucher::create([
        'scope' => VoucherScope::Platform,
        'store_id' => null,
        'code' => 'TWICE',
        'type' => VoucherType::Fixed,
        'value_sen' => 500,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Vouchers::class)
        ->call('create')
        ->set('code', 'twice')
        ->set('type', 'fixed')
        ->set('value', '1.00')
        ->set('startsAt', '2026-06-01T00:00')
        ->set('endsAt', '2026-12-31T23:59')
        ->call('save')
        ->assertHasErrors(['code']);

    expect(Voucher::count())->toBe(1);
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on every content route', function () {
    test()->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    foreach (['admin.content.banners', 'admin.content.home-sections', 'admin.content.pages', 'admin.content.vouchers'] as $route) {
        test()->actingAs($buyer)->get(route($route))->assertForbidden();
    }
});

test('admins can open every content screen', function () {
    $admin = contentAdmin();

    foreach (['admin.content.banners', 'admin.content.home-sections', 'admin.content.pages', 'admin.content.vouchers'] as $route) {
        test()->actingAs($admin)->get(route($route))->assertOk();
    }
});
