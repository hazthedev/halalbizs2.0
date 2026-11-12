<?php

use App\Livewire\Admin\System\Settings;
use App\Models\Banner;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ThemeAsset;
use App\Models\User;
use App\Settings\MobileAppSettings;
use App\Settings\ThemeSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function homeSection(string $type, int $position, array $attributes = []): HomeSection
{
    return HomeSection::create($attributes + [
        'type' => $type,
        'title' => ['en' => ucfirst($type), 'ms' => 'BM '.$type],
        'payload' => [],
        'position' => $position,
        'is_active' => true,
    ]);
}

// ── Item 1: the app's home follows the admin's sections ────────────────────

test('home blocks follow the admin order and titles; hidden and empty sections are left out', function () {
    $product = Product::factory()->create(['sold_count' => 9]);
    $category = Category::factory()->create(['parent_id' => null, 'is_active' => true]);

    homeSection('product_grid', 3, ['title' => ['en' => 'Popular now', 'ms' => 'Popular sekarang'], 'payload' => ['source' => 'top', 'limit' => 4]]);
    homeSection('category_grid', 1, ['title' => ['en' => 'Shop by category']]);
    homeSection('recommended', 2);
    homeSection('product_carousel', 4, ['is_active' => false]);
    homeSection('banner', 5); // no banners exist: empty, so dropped (same as the web home)

    $blocks = $this->getJson('/api/v1/home')->assertOk()->json('data.blocks');

    expect(array_column($blocks, 'type'))->toBe(['category_grid', 'recommended', 'product_grid'])
        ->and($blocks[0]['title'])->toBe('Shop by category')
        ->and(array_column($blocks[0]['categories'], 'id'))->toContain($category->id)
        ->and($blocks[0]['categories'][0])->toHaveKeys(['id', 'slug', 'name', 'parent_id', 'image'])
        ->and($blocks[2]['title'])->toBe('Popular now')
        ->and($blocks[2]['source'])->toBe('top')
        ->and($blocks[2]['products'][0]['id'])->toBe($product->id);

    $malay = $this->getJson('/api/v1/home', ['Accept-Language' => 'ms'])->json('data.blocks');

    expect($malay[2]['title'])->toBe('Popular sekarang');
});

test('a banner block carries the active banners with subtitle and button text', function () {
    homeSection('banner', 1);
    Banner::create([
        'title' => ['en' => 'Raya sale'], 'subtitle' => ['en' => 'Up to 50% off'], 'cta_label' => ['en' => 'Shop now'],
        'link_url' => '/c/dates', 'position' => 1, 'is_active' => true,
    ]);

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.blocks.0.type', 'banner')
        ->assertJsonPath('data.blocks.0.banners.0.title', 'Raya sale')
        ->assertJsonPath('data.blocks.0.banners.0.subtitle', 'Up to 50% off')
        ->assertJsonPath('data.blocks.0.banners.0.cta_label', 'Shop now')
        ->assertJsonPath('data.blocks.0.banners.0.app_url', '/c/dates')
        ->assertJsonPath('data.banners.0.title', 'Raya sale'); // the old key stays (add-only)
});

// ── Item 2: announcement bar and occasion hero reach the app ───────────────

test('home carries the announcement bar and the occasion hero only while they are on', function () {
    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.announcement', null)
        ->assertJsonPath('data.hero', null);

    Storage::fake('public');
    ThemeAsset::hero()->addMedia(UploadedFile::fake()->image('hero.jpg', 1600, 600))->toMediaCollection('image');

    $theme = app(ThemeSettings::class);
    $theme->occasion = 'raya';
    $theme->announcement_enabled = true;
    $theme->announcement_text_en = 'Free shipping this week';
    $theme->announcement_text_ms = 'Penghantaran percuma minggu ini';
    $theme->hero_image_enabled = true;
    $theme->save();

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.announcement.text', 'Free shipping this week')
        ->assertJsonPath('data.announcement.bg', '#03392B')
        ->assertJsonPath('data.announcement.text_color', '#F7F7F4')
        ->assertJsonPath('data.hero.occasion', 'raya')
        ->assertJsonStructure(['data' => ['hero' => ['image']]]);

    $this->getJson('/api/v1/home', ['Accept-Language' => 'ms'])
        ->assertJsonPath('data.announcement.text', 'Penghantaran percuma minggu ini');

    $theme->ends_at = now()->subDay()->toIso8601String();
    $theme->save();

    $this->getJson('/api/v1/home')
        ->assertJsonPath('data.announcement', null)
        ->assertJsonPath('data.hero', null);
});

// ── Item 10: cached a few minutes, cleared when an admin saves ─────────────

test('home data is served from cache until content is saved or five minutes pass', function () {
    $section = homeSection('recommended', 1, ['title' => ['en' => 'For you']]);

    $this->getJson('/api/v1/home')->assertJsonPath('data.blocks.0.title', 'For you');

    // A write that fires no model event: the cached copy is still served.
    DB::table('home_sections')->where('id', $section->id)->update(['title' => json_encode(['en' => 'Behind the cache'])]);
    $this->getJson('/api/v1/home')->assertJsonPath('data.blocks.0.title', 'For you');

    $this->travel(6)->minutes();
    $this->getJson('/api/v1/home')->assertJsonPath('data.blocks.0.title', 'Behind the cache');

    // An admin save clears it at once.
    $section->refresh()->update(['title' => ['en' => 'Picked for you']]);
    $this->getJson('/api/v1/home')->assertJsonPath('data.blocks.0.title', 'Picked for you');

    // So does a theme settings save.
    $theme = app(ThemeSettings::class);
    $theme->announcement_enabled = true;
    $theme->announcement_text_en = 'Hello';
    $theme->save();
    $this->getJson('/api/v1/home')->assertJsonPath('data.announcement.text', 'Hello');
});

test('an unchanged home answers 304 to a matching If-None-Match', function () {
    homeSection('recommended', 1);

    $etag = $this->getJson('/api/v1/home')->assertOk()->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson('/api/v1/home', ['If-None-Match' => $etag])->assertStatus(304);
});

// ── Item 9: minimum app version and maintenance notice ─────────────────────

test('config carries the minimum app version and maintenance notice an admin set', function () {
    $this->getJson('/api/v1/config')
        ->assertJsonPath('data.min_app_version', '1.0.0')
        ->assertJsonPath('data.maintenance', ['enabled' => false, 'message' => '']);

    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('minAppVersion', '1.4.0')
        ->set('appMaintenanceEnabled', true)
        ->set('appMaintenanceMessageEn', 'Back at 3 pm')
        ->set('appMaintenanceMessageMs', 'Kembali pukul 3 petang')
        ->call('saveMobileApp')
        ->assertHasNoErrors();

    expect(app(MobileAppSettings::class)->refresh()->min_version)->toBe('1.4.0');

    $this->getJson('/api/v1/config')
        ->assertJsonPath('data.min_app_version', '1.4.0')
        ->assertJsonPath('data.maintenance', ['enabled' => true, 'message' => 'Back at 3 pm']);

    $this->getJson('/api/v1/config', ['Accept-Language' => 'ms'])
        ->assertJsonPath('data.maintenance.message', 'Kembali pukul 3 petang');
});

test('the minimum app version must look like 1.2.3', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('minAppVersion', 'latest')
        ->call('saveMobileApp')
        ->assertHasErrors('minAppVersion');
});
