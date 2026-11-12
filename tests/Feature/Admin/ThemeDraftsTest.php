<?php

use App\Livewire\Admin\Content\Theme;
use App\Models\ThemeAsset;
use App\Models\User;
use App\Settings\ThemeSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// Same people as ContentDraftsTest's helpers, under other names so this file runs alone.
if (! function_exists('draftEditor')) {
    function draftEditor(): User
    {
        $user = User::factory()->create(['two_factor_method' => 'email']);
        $user->assignRole('admin');
        $user->syncPermissions(['cms.manage']);

        return $user->fresh();
    }

    function draftPublisher(): User
    {
        return makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
    }
}

function liveBar(): void
{
    $settings = app(ThemeSettings::class);
    $settings->announcement_enabled = true;
    $settings->announcement_text_en = 'Old bar words';
    $settings->save();
}

test('a theme save is a draft; the live bar and app feed keep the old words', function () {
    liveBar();

    Livewire::actingAs(draftEditor())->test(Theme::class)
        ->set('announcementTextEn', 'New bar words')->call('save')->assertHasNoErrors();

    expect(app(ThemeSettings::class)->refresh()->announcement_text_en)->toBe('Old bar words')
        ->and(ThemeAsset::hero()->draft)->not->toBeNull();

    $this->get('/')->assertSee('Old bar words')->assertDontSee('New bar words');
    $this->getJson('/api/v1/home')->assertJsonPath('data.announcement.text', 'Old bar words');
});

test('?preview=1 shows the drafted bar to the editor; shoppers and guests see live; nothing leaks', function () {
    liveBar();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(Theme::class)->set('announcementTextEn', 'New bar words')->call('save');

    $this->actingAs($editor)->get('/?preview=1')->assertSee('New bar words');
    expect(app(ThemeSettings::class)->announcement_text_en)->toBe('Old bar words');
    $this->actingAs($editor)->getJson('/api/v1/home?preview=1')->assertJsonPath('data.announcement.text', 'New bar words');

    auth()->logout();
    $this->getJson('/api/v1/home')->assertJsonPath('data.announcement.text', 'Old bar words');
    $this->actingAs(User::factory()->create())->get('/?preview=1')->assertSee('Old bar words')->assertDontSee('New bar words');
});

test('a publisher publishes the theme; live and the app cache are fresh and the change is logged', function () {
    liveBar();

    Livewire::actingAs(draftEditor())->test(Theme::class)->set('announcementTextEn', 'New bar words')->call('save');
    $this->getJson('/api/v1/home')->assertJsonPath('data.announcement.text', 'Old bar words');

    $hero = ThemeAsset::hero();
    Livewire::actingAs(draftPublisher())->test(Theme::class)->assertSee('Draft')->call('publishDraft', 'theme', $hero->id);

    expect($hero->fresh()->draft)->toBeNull();
    $this->getJson('/api/v1/home')->assertJsonPath('data.announcement.text', 'New bar words');
    $this->get('/')->assertSee('New bar words');
    expect(Activity::where('log_name', 'theme')->count())->toBe(1);
});

test('the hero picture is drafted, shown in preview only, and a reset is a draft too', function () {
    Storage::fake('public');
    $settings = app(ThemeSettings::class);
    $settings->hero_image_enabled = true;
    $settings->save();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(Theme::class)
        ->set('heroImage', UploadedFile::fake()->image('hero.jpg', 1600, 400))->call('save')->assertHasNoErrors();

    $hero = ThemeAsset::hero();
    expect($hero->fresh()->getMedia('image'))->toHaveCount(0)->and($hero->draft->getMedia('image'))->toHaveCount(1);
    $this->get('/')->assertDontSee('hero.jpg');
    $this->actingAs($editor)->get('/?preview=1')->assertSee('hero');

    Livewire::actingAs($editor)->test(Theme::class)->call('resetDefaults');
    expect(app(ThemeSettings::class)->refresh()->hero_image_enabled)->toBeTrue();
});

test('removing the hero picture keeps the words already drafted', function () {
    $publisher = contentPublisher();

    Livewire::actingAs($publisher)->test(Theme::class)
        ->set('announcementEnabled', true)
        ->set('announcementTextEn', 'Drafted words')
        ->call('save')
        ->call('removeHeroImage');

    expect(ThemeAsset::hero()->draft->payload['announcement_text_en'])->toBe('Drafted words');
});
