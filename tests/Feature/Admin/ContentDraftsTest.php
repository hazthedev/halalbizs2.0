<?php

use App\Livewire\Admin\Content\Pages;
use App\Models\Banner;
use App\Models\Page;
use App\Models\User;
use App\Services\ContentDrafts;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** Staff who may write content but not publish it. */
function contentEditor(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions(['cms.manage']);

    return $user->fresh();
}

function contentPublisher(): User
{
    return makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
}

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
