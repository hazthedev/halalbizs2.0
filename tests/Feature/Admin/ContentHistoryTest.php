<?php

use App\Enums\HelpCategory;
use App\Livewire\Admin\Content\Banners;
use App\Livewire\Admin\Content\HomeSections;
use App\Livewire\Admin\Content\Pages;
use App\Livewire\Admin\Content\Theme;
use App\Livewire\Admin\Support\Articles;
use App\Livewire\Admin\System\AuditLog;
use App\Models\Banner;
use App\Models\HelpArticle;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// CMS item 7: every content change records who made it, what it was and when.

function historyAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email', 'name' => 'Aisyah Editor']);
    makeAdmin($user);

    return $user;
}

test('editing a page records who changed it, with the old and new text', function () {
    $admin = historyAdmin();
    $page = Page::create(['slug' => 'about', 'title' => ['en' => 'About us'], 'body' => ['en' => '<p>Old</p>'], 'is_active' => true]);

    Livewire::actingAs($admin)->test(Pages::class)
        ->call('edit', $page->id)
        ->set('title.en', 'About HalalBizs')
        ->set('body.en', '<p>New</p>')
        ->call('save')
        ->assertHasNoErrors();

    $entry = Activity::where('subject_type', Page::class)->where('event', 'updated')->sole();

    expect($entry->causer_id)->toBe($admin->id)
        // Translated fields are logged as their stored JSON (all languages in one value).
        ->and(json_decode($entry->attribute_changes['old']['title'], true)['en'])->toBe('About us')
        ->and(json_decode($entry->attribute_changes['attributes']['title'], true)['en'])->toBe('About HalalBizs')
        ->and(json_decode($entry->attribute_changes['attributes']['body'], true)['en'])->toBe('<p>New</p>');
});

test('banner, home section and help article changes are recorded too', function () {
    $admin = historyAdmin();
    $banner = Banner::create(['title' => ['en' => 'Raya'], 'position' => 1, 'is_active' => true]);
    $section = HomeSection::create(['type' => 'recommended', 'title' => ['en' => 'For you'], 'payload' => [], 'position' => 1, 'is_active' => true]);
    $article = HelpArticle::create(['category' => HelpCategory::Buying, 'title' => ['en' => 'Track'], 'body' => ['en' => '<p>x</p>'], 'position' => 1, 'is_active' => true]);
    Activity::query()->delete(); // only what the admin does next

    Livewire::actingAs($admin)->test(Banners::class)->call('toggleActive', $banner->id)->call('publishDraft', 'banner', $banner->id);
    Livewire::actingAs($admin)->test(HomeSections::class)->call('toggleActive', $section->id);
    Livewire::actingAs($admin)->test(Articles::class)->call('delete', $article->id);

    expect(Activity::where('causer_id', $admin->id)->orderBy('id')->get()->map(fn (Activity $a) => [class_basename($a->subject_type), $a->event])->all())
        ->toBe([['Banner', 'updated'], ['HomeSection', 'updated'], ['HelpArticle', 'deleted']]);

    expect(Activity::where('subject_type', Banner::class)->sole()->attribute_changes['attributes'])->toBe(['is_active' => false]);
});

test('saving the announcement bar records the settings that changed', function () {
    $admin = historyAdmin();

    Livewire::actingAs($admin)->test(Theme::class)
        ->set('announcementEnabled', true)
        ->set('announcementTextEn', 'Free shipping this week')
        ->call('save')
        ->assertHasNoErrors();

    $entry = Activity::where('log_name', 'theme')->sole();

    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->attribute_changes['old']['announcement_text_en'])->toBe('')
        ->and($entry->attribute_changes['attributes']['announcement_text_en'])->toBe('Free shipping this week')
        ->and($entry->attribute_changes['attributes'])->not->toHaveKey('announcement_bg'); // unchanged, so not listed
});

test('the audit log lists content changes and filters by content type', function () {
    $admin = historyAdmin();
    $page = Page::create(['slug' => 'about', 'title' => ['en' => 'About us'], 'body' => ['en' => '<p>Old</p>'], 'is_active' => true]);
    Banner::create(['title' => ['en' => 'Raya'], 'position' => 1, 'is_active' => true]);

    Livewire::actingAs($admin)->test(Pages::class)->call('toggleActive', $page->id);

    Livewire::actingAs($admin)->test(AuditLog::class)
        ->assertSee('Aisyah Editor')
        ->set('subjectType', 'Page')
        ->assertSee('Page')
        ->assertSeeInOrder(['is_active:', 'true', 'changed to', 'false']) // the change itself: old then new
        ->assertSee('Aisyah Editor')
        ->assertDontSee('Banner #')
        ->set('subjectType', 'Theme')
        ->assertDontSee('Aisyah Editor');
});
