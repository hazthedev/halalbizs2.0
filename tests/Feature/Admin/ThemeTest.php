<?php

use App\Livewire\Admin\Content\Theme;
use App\Models\ThemeAsset;
use App\Models\User;
use App\Settings\ThemeSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function themeAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

function themeSettings(array $overrides = []): ThemeSettings
{
    $settings = app(ThemeSettings::class);

    foreach ($overrides as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();

    return $settings;
}

// ── Admin save ──────────────────────────────────────────────────────────

test('admin theme save persists all settings', function () {
    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->set('occasion', 'Hari Raya Aidilfitri')
        ->set('announcementEnabled', true)
        ->set('announcementTextEn', 'Raya sale — free shipping over RM40')
        ->set('announcementTextMs', 'Jualan Raya — penghantaran percuma melebihi RM40')
        ->set('announcementBg', '#7c2d12')
        ->set('announcementTextColor', '#fff7ed')
        ->set('heroImageEnabled', true)
        ->set('startsAt', '2026-06-01T00:00')
        ->set('endsAt', '2026-06-30T23:59')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(ThemeSettings::class)->refresh();

    expect($settings->occasion)->toBe('Hari Raya Aidilfitri')
        ->and($settings->announcement_enabled)->toBeTrue()
        ->and($settings->announcement_text_en)->toBe('Raya sale — free shipping over RM40')
        ->and($settings->announcement_text_ms)->toBe('Jualan Raya — penghantaran percuma melebihi RM40')
        ->and($settings->announcement_bg)->toBe('#7C2D12')
        ->and($settings->announcement_text_color)->toBe('#FFF7ED')
        ->and($settings->hero_image_enabled)->toBeTrue()
        ->and($settings->starts_at)->toContain('2026-06-01')
        ->and($settings->ends_at)->toContain('2026-06-30');
});

test('colors must be 6-digit hex values', function () {
    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->set('announcementBg', 'red')
        ->set('announcementTextColor', '#FFF')
        ->call('save')
        ->assertHasErrors(['announcementBg', 'announcementTextColor']);
});

test('the occasion window must end after it starts', function () {
    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->set('startsAt', '2026-06-30T00:00')
        ->set('endsAt', '2026-06-01T00:00')
        ->call('save')
        ->assertHasErrors(['endsAt']);
});

test('announcement text in english is required when the bar is enabled', function () {
    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->set('announcementEnabled', true)
        ->set('announcementTextEn', '')
        ->call('save')
        ->assertHasErrors(['announcementTextEn' => 'required']);
});

test('admin uploads a hero image', function () {
    Storage::fake('public');

    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->set('heroImage', UploadedFile::fake()->image('raya-hero.jpg', 1600, 600))
        ->call('save')
        ->assertHasNoErrors();

    expect(ThemeAsset::hero()->getFirstMedia('image'))->not->toBeNull();
});

test('reset to defaults restores stock values and clears the hero image', function () {
    Storage::fake('public');

    themeSettings([
        'occasion' => 'Merdeka',
        'announcement_enabled' => true,
        'announcement_text_en' => 'Merdeka deals',
        'announcement_bg' => '#112233',
    ]);
    ThemeAsset::hero()->addMedia(UploadedFile::fake()->image('hero.jpg'))->toMediaCollection('image');

    Livewire::actingAs(themeAdmin())
        ->test(Theme::class)
        ->call('resetDefaults');

    $settings = app(ThemeSettings::class)->refresh();

    expect($settings->occasion)->toBe('')
        ->and($settings->announcement_enabled)->toBeFalse()
        ->and($settings->announcement_text_en)->toBe('')
        ->and($settings->announcement_bg)->toBe('#03392B')
        ->and($settings->announcement_text_color)->toBe('#F7F7F4')
        ->and($settings->hero_image_enabled)->toBeFalse()
        ->and($settings->starts_at)->toBeNull()
        ->and($settings->ends_at)->toBeNull()
        ->and(ThemeAsset::hero()->getFirstMedia('image'))->toBeNull();
});

// ── Storefront announcement bar ─────────────────────────────────────────

test('announcement bar renders with custom colors when enabled and within schedule', function () {
    themeSettings([
        'announcement_enabled' => true,
        'announcement_text_en' => 'Raya sale — free shipping over RM40',
        'announcement_text_ms' => 'Jualan Raya — penghantaran percuma melebihi RM40',
        'announcement_bg' => '#7C2D12',
        'announcement_text_color' => '#FFF7ED',
        'starts_at' => now()->subDay()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Raya sale — free shipping over RM40')
        ->assertSee('background-color: #7C2D12', false)
        ->assertSee('color: #FFF7ED', false);
});

test('announcement bar shows the ms text under the ms locale', function () {
    themeSettings([
        'announcement_enabled' => true,
        'announcement_text_en' => 'Raya sale — free shipping over RM40',
        'announcement_text_ms' => 'Jualan Raya — penghantaran percuma melebihi RM40',
    ]);

    $this->withSession(['locale' => 'ms'])
        ->get('/')
        ->assertOk()
        ->assertSee('Jualan Raya — penghantaran percuma melebihi RM40');
});

test('announcement bar is hidden when disabled', function () {
    themeSettings([
        'announcement_enabled' => false,
        'announcement_text_en' => 'Raya sale — free shipping over RM40',
    ]);

    $this->get('/')->assertOk()->assertDontSee('Raya sale — free shipping over RM40');
});

test('announcement bar is hidden outside the schedule window', function () {
    themeSettings([
        'announcement_enabled' => true,
        'announcement_text_en' => 'Raya sale — free shipping over RM40',
        'starts_at' => now()->subDays(10)->toIso8601String(),
        'ends_at' => now()->subDays(3)->toIso8601String(),
    ]);

    $this->get('/')->assertOk()->assertDontSee('Raya sale — free shipping over RM40');

    themeSettings([
        'starts_at' => now()->addDays(3)->toIso8601String(),
        'ends_at' => now()->addDays(10)->toIso8601String(),
    ]);

    $this->get('/')->assertOk()->assertDontSee('Raya sale — free shipping over RM40');
});

// ── Storefront hero ─────────────────────────────────────────────────────

test('home hero renders when enabled with an uploaded image', function () {
    Storage::fake('public');

    themeSettings([
        'occasion' => 'Pesta Hujung Tahun',
        'hero_image_enabled' => true,
    ]);
    ThemeAsset::hero()->addMedia(UploadedFile::fake()->image('occasion-hero.jpg', 1600, 600))->toMediaCollection('image');

    $this->get('/')
        ->assertOk()
        ->assertSee('Pesta Hujung Tahun')
        ->assertSee('occasion-hero', false);
});

test('home hero is hidden when disabled or without an image', function () {
    Storage::fake('public');

    // Enabled but no image uploaded — nothing renders.
    themeSettings([
        'occasion' => 'Pesta Hujung Tahun',
        'hero_image_enabled' => true,
    ]);

    $this->get('/')->assertOk()->assertDontSee('Pesta Hujung Tahun');

    // Image present but the feature is off.
    ThemeAsset::hero()->addMedia(UploadedFile::fake()->image('occasion-hero.jpg'))->toMediaCollection('image');
    themeSettings(['hero_image_enabled' => false]);

    $this->get('/')->assertOk()->assertDontSee('occasion-hero');
});

test('home hero is hidden outside the schedule window', function () {
    Storage::fake('public');

    themeSettings([
        'occasion' => 'Pesta Hujung Tahun',
        'hero_image_enabled' => true,
        'starts_at' => now()->subDays(10)->toIso8601String(),
        'ends_at' => now()->subDays(3)->toIso8601String(),
    ]);
    ThemeAsset::hero()->addMedia(UploadedFile::fake()->image('occasion-hero.jpg'))->toMediaCollection('image');

    $this->get('/')->assertOk()->assertDontSee('Pesta Hujung Tahun');
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on the admin theme route', function () {
    $this->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('admin.content.theme'))->assertForbidden();
});

test('admins can open the theme screen', function () {
    $this->actingAs(themeAdmin())->get(route('admin.content.theme'))->assertOk();
});
