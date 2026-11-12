<?php

use App\Livewire\Admin\Content\Pages;
use App\Models\Banner;
use App\Models\Page;
use App\Models\User;
use App\Services\ContentDrafts;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function aboutPage(): Page
{
    return Page::create(['slug' => 'about', 'title' => ['en' => 'About us'], 'body' => ['en' => '<p>Old words</p>'], 'is_active' => true]);
}

// ── Item 11: a save is a draft; the live page keeps showing until publish ──

test('an edit is saved as a draft and shoppers keep seeing the live page', function () {
    $page = aboutPage();

    Livewire::actingAs(contentEditor())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', '<p>New words</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>Old words</p>')
        ->and($page->fresh()->draft)->not->toBeNull();

    $this->get('/page/about')->assertOk()->assertSee('Old words')->assertDontSee('New words');
});

test('a publisher makes the draft live; it is kept as a version and logged', function () {
    $page = aboutPage();
    $publisher = contentPublisher();

    Livewire::actingAs($publisher)
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', '<p>New words</p>')
        ->call('save')
        ->call('publishDraft', 'page', $page->id);

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>New words</p>')
        ->and($page->fresh()->draft)->toBeNull()
        // The state before the change and the one just published.
        ->and($page->versions()->count())->toBe(2)
        ->and($page->versions()->first()->published_by)->toBe($publisher->id);

    $this->get('/page/about')->assertSee('New words');

    $this->assertDatabaseHas('activity_log', ['subject_type' => $page->getMorphClass(), 'subject_id' => $page->id, 'event' => 'updated', 'causer_id' => $publisher->id]);
});

// ── Item 18: an editor writes drafts, only a publisher makes them live ─────

test('an editor cannot publish, delete, or throw away a draft', function () {
    $page = Page::create(['slug' => 'careers', 'title' => ['en' => 'Careers'], 'body' => ['en' => '<p>Old words</p>'], 'is_active' => true]);

    $editor = contentEditor();

    Livewire::actingAs($editor)->test(Pages::class)->call('edit', $page->id)->set('body.en', '<p>New words</p>')->call('save');

    Livewire::actingAs($editor)->test(Pages::class)->call('publishDraft', 'page', $page->id)->assertForbidden();
    Livewire::actingAs($editor)->test(Pages::class)->call('discardDraft', 'page', $page->id)->assertForbidden();
    Livewire::actingAs($editor)->test(Pages::class)->call('delete', $page->id)->assertForbidden();

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>Old words</p>')
        ->and($page->fresh()->draft)->not->toBeNull();
});

test('a new page stays hidden until it is published', function () {
    Livewire::actingAs(contentEditor())
        ->test(Pages::class)
        ->call('create')
        ->set('slug', 'careers')
        ->set('title.en', 'Careers')
        ->set('body.en', '<p>Join us</p>')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::where('slug', 'careers')->firstOrFail();

    $this->get('/page/careers')->assertNotFound();

    Livewire::actingAs(contentPublisher())->test(Pages::class)->call('publishDraft', 'page', $page->id);

    $this->get('/page/careers')->assertOk()->assertSee('Join us');
});

test('switching a page off is a draft too, and a draft can be thrown away', function () {
    $page = Page::create(['slug' => 'careers', 'title' => ['en' => 'Careers'], 'body' => ['en' => '<p>Join us</p>'], 'is_active' => true]);
    $publisher = contentPublisher();

    $screen = Livewire::actingAs($publisher)->test(Pages::class)->call('toggleActive', $page->id);

    expect($page->fresh()->is_active)->toBeTrue();
    $this->get('/page/careers')->assertOk();

    $screen->call('discardDraft', 'page', $page->id);
    expect($page->fresh()->draft)->toBeNull();

    $screen->call('toggleActive', $page->id)->call('publishDraft', 'page', $page->id);
    $this->get('/page/careers')->assertNotFound();
});

// ── Item 11: preview shows the draft to staff only ─────────────────────────

test('staff see the draft with ?preview=1; shoppers and guests never do', function () {
    $page = aboutPage();
    $editor = contentEditor();

    Livewire::actingAs($editor)->test(Pages::class)->call('edit', $page->id)->set('body.en', '<p>New words</p>')->call('save');

    $this->actingAs($editor)->get('/page/about?preview=1')->assertOk()->assertSee('New words')->assertSee(__('Preview'));
    $this->actingAs($editor)->get('/page/about')->assertSee('Old words');

    $this->actingAs(User::factory()->create())->get('/page/about?preview=1')->assertSee('Old words')->assertDontSee('New words');
    auth()->logout();
    $this->get('/page/about?preview=1')->assertSee('Old words')->assertDontSee('New words');
});

// ── The seam itself: a draft carries its pictures until publish ────────────

test('a picture uploaded with a draft shows in preview only, and moves to the item at publish', function () {
    Storage::fake('public');
    $banner = Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true]);
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 1500, 500))->toMediaCollection('image');
    $drafts = app(ContentDrafts::class);

    $draft = $drafts->stage($banner->fresh(), contentEditor(), withFiles: true);
    $draft->addMedia(UploadedFile::fake()->image('new.jpg', 1500, 500))->toMediaCollection('image');

    expect($banner->fresh()->getFirstMedia('image')->file_name)->toBe('old.jpg')
        ->and($drafts->preview($banner->fresh())->getFirstMedia('image')->file_name)->toBe('new.jpg');

    // Saving words again keeps the uploaded picture with the draft.
    $again = $banner->fresh();
    $again->link_url = '/flash-sale';
    $drafts->stage($again, contentEditor());

    $drafts->publish($banner, contentPublisher());

    expect($banner->fresh()->getFirstMedia('image')->file_name)->toBe('new.jpg')
        ->and($banner->fresh()->getMedia('image'))->toHaveCount(1)
        ->and($banner->fresh()->link_url)->toBe('/flash-sale')
        ->and($banner->fresh()->draft)->toBeNull();
});

test('removing a picture is drafted too', function () {
    Storage::fake('public');
    $banner = Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true]);
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 1500, 500))->toMediaCollection('image_vi');
    $drafts = app(ContentDrafts::class);

    $drafts->stage($banner->fresh(), contentEditor(), clearMedia: ['image_vi']);

    expect($banner->fresh()->getMedia('image_vi'))->toHaveCount(1)
        ->and($drafts->preview($banner->fresh())->getMedia('image_vi'))->toHaveCount(0);

    $drafts->publish($banner, contentPublisher());

    expect($banner->fresh()->getMedia('image_vi'))->toHaveCount(0);
});

// ── Item 11: the preview screen shows the real page at web and phone size ──

test('the preview screen frames the real page twice, with drafts on, and only our own pages', function () {
    $editor = contentEditor();

    $this->actingAs($editor)->get('/admin/content/preview?path=/page/about')
        ->assertOk()
        ->assertSee('src="'.url('/page/about').'?preview=1"', false)
        ->assertSee('data-preview="web"', false)
        ->assertSee('data-preview="phone"', false);

    // The browser must be allowed to frame our own pages here, and only here.
    expect($this->actingAs($editor)->get('/admin/content/preview')->headers->get('Content-Security-Policy'))->toContain("frame-src 'self' ")
        ->and($this->actingAs($editor)->get('/admin/content/pages')->headers->get('Content-Security-Policy'))->not->toContain("frame-src 'self'");

    // A path with its own query keeps it; anything that is not a path on this site falls back to home.
    $this->actingAs($editor)->get('/admin/content/preview?path='.urlencode('/search?q=dates'))
        ->assertSee('src="'.url('/search').'?q=dates&amp;preview=1"', false);
    $this->actingAs($editor)->get('/admin/content/preview?path='.urlencode('//evil.example/x'))
        ->assertSee('src="'.url('/').'?preview=1"', false);
    $this->actingAs($editor)->get('/admin/content/preview?path='.urlencode('https://evil.example'))
        ->assertSee('src="'.url('/').'?preview=1"', false);
});

test('the pages list marks a pending draft and offers publish only to a publisher', function () {
    $page = aboutPage();
    $editor = contentEditor();

    Livewire::actingAs($editor)->test(Pages::class)->call('edit', $page->id)->set('title.en', 'About HalalBizs')->call('save')
        ->assertSee(__('Draft'))
        ->assertSee('About HalalBizs')
        ->assertSee(__('Preview'))
        ->assertDontSeeHtml('publishDraft(')
        ->assertDontSeeHtml('wire:click="delete(');

    Livewire::actingAs(contentPublisher())->test(Pages::class)
        ->assertSeeHtml("publishDraft('page', {$page->id})")
        ->assertSee(__('Throw away'));
});

test('the app is told whether the signed-in person may preview drafts', function () {
    Sanctum::actingAs(contentEditor());
    $this->getJson('/api/v1/me')->assertJsonPath('data.can_preview_content', true);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/me')->assertJsonPath('data.can_preview_content', false);
});

// ── Preview on the API (the app), and rules a preview must still obey ──────

test('the API shows drafts only to a staff token with ?preview=1, and lists stay lists', function () {
    $page = aboutPage();
    Page::create(['slug' => 'aaa-first', 'title' => ['en' => 'Later id, same list'], 'body' => ['en' => '<p>x</p>'], 'is_active' => true]);
    $editor = contentEditor();

    Livewire::actingAs($editor)->test(Pages::class)->call('edit', $page->id)->set('title.en', 'About HalalBizs')->call('save');

    auth()->guard('web')->logout(); // the Livewire call above signed the editor in on this test's session
    $this->getJson('/api/v1/pages/about?preview=1')->assertJsonPath('data.title', 'About us');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/pages/about?preview=1')->assertJsonPath('data.title', 'About us');

    Sanctum::actingAs($editor);
    $this->getJson('/api/v1/pages/about')->assertJsonPath('data.title', 'About us');
    $this->getJson('/api/v1/pages/about?preview=1')->assertJsonPath('data.title', 'About HalalBizs');

    $list = $this->getJson('/api/v1/pages?preview=1')->json('data');
    expect(array_is_list($list))->toBeTrue()
        ->and(array_column($list, 'title'))->toBe(['About HalalBizs', 'Later id, same list']);
});

test('a preview still hides a banner outside its dates', function () {
    Banner::create(['title' => ['en' => 'Next month'], 'position' => 1, 'is_active' => true, 'starts_at' => now()->addMonth()]);
    $now = Banner::create(['title' => ['en' => 'Now'], 'position' => 2, 'is_active' => true]);

    $drafts = app(ContentDrafts::class);
    $drafts->previewing = true;

    expect($drafts->visible(Banner::class)->pluck('id')->all())->toBe([$now->id]);
});

test('the migration gives publish rights to everyone who could manage content, and to nobody else', function () {
    $content = contentEditor();
    $other = User::factory()->create();
    $other->givePermissionTo('orders.manage');
    $migration = require database_path('migrations/2026_11_12_170001_grant_cms_publish_to_content_staff.php');

    $migration->down();
    expect($content->fresh()->can('cms.publish'))->toBeFalse();

    $migration->up();
    $migration->up(); // safe to run twice

    expect($content->fresh()->can('cms.publish'))->toBeTrue()
        ->and($content->fresh()->can('cms.manage'))->toBeTrue()
        ->and($other->fresh()->can('cms.publish'))->toBeFalse();
});
