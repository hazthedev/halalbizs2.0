<?php

use App\Livewire\Admin\Content\History;
use App\Livewire\Admin\Content\Pages;
use App\Models\Banner;
use App\Models\ContentVersion;
use App\Models\Page;
use App\Models\User;
use App\Services\ContentDrafts;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** Publish a new body on a page through the real admin screen. */
function publishBody(Page $page, User $by, string $body): void
{
    Livewire::actingAs($by)->test(Pages::class)
        ->call('edit', $page->id)->set('body.en', $body)->call('save')
        ->call('publishDraft', 'page', $page->id);
}

function historyPage(): Page
{
    return Page::create(['slug' => 'about', 'title' => ['en' => 'About us'], 'body' => ['en' => '<p>First</p>'], 'is_active' => true]);
}

// ── Item 13: restore an earlier version ────────────────────────────────────

test('restoring an earlier version makes a draft; live changes only when it is published', function () {
    $page = historyPage();
    $publisher = contentPublisher();
    publishBody($page, $publisher, '<p>Second</p>');
    publishBody($page, $publisher, '<p>Third</p>');

    $first = $page->versions()->get()->last(); // what was live before the first change

    Livewire::actingAs(contentEditor())
        ->test(History::class, ['type' => 'page', 'id' => $page->id])
        ->assertSee($publisher->name)
        ->call('restore', $first->id)
        ->assertHasNoErrors();

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>Third</p>')
        ->and(app(ContentDrafts::class)->preview($page->fresh())->getTranslation('body', 'en'))->toBe('<p>First</p>');

    Livewire::actingAs($publisher)->test(Pages::class)->call('publishDraft', 'page', $page->id);

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>First</p>');
});

test('only a superadmin can restore straight to live', function () {
    $page = historyPage();
    $publisher = contentPublisher();
    publishBody($page, $publisher, '<p>Second</p>');
    $first = $page->versions()->get()->last();

    Livewire::actingAs($publisher)->test(History::class, ['type' => 'page', 'id' => $page->id])
        ->call('restore', $first->id, true)
        ->assertForbidden();
    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>Second</p>');

    $super = User::factory()->create(['is_superadmin' => true, 'two_factor_method' => 'email']);
    $super->assignRole('admin');

    Livewire::actingAs($super)->test(History::class, ['type' => 'page', 'id' => $page->id])
        ->call('restore', $first->id, true);

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>First</p>')
        ->and($page->fresh()->draft)->toBeNull();
});

test('a version of another item cannot be restored onto this one', function () {
    $page = historyPage();
    $other = Page::create(['slug' => 'faq', 'title' => ['en' => 'FAQ'], 'body' => ['en' => '<p>Other</p>'], 'is_active' => true]);
    $publisher = contentPublisher();
    publishBody($other, $publisher, '<p>Other two</p>');

    Livewire::actingAs($publisher)->test(History::class, ['type' => 'page', 'id' => $page->id])
        ->call('restore', $other->versions()->first()->id)
        ->assertNotFound();
});

test('only the last 20 versions of an item are kept', function () {
    $page = historyPage();
    $drafts = app(ContentDrafts::class);
    $publisher = contentPublisher();

    foreach (range(1, 23) as $n) {
        $edit = $page->fresh();
        $edit->setTranslation('body', 'en', "<p>Body {$n}</p>");
        $drafts->stage($edit, $publisher);
        $drafts->publish($page, $publisher);
    }

    expect($page->versions()->count())->toBe(20)
        ->and($page->versions()->first()->payload['body'])->toContain('Body 23');
});

test('the history says who published, when, and which fields changed', function () {
    $page = historyPage();
    $publisher = contentPublisher();
    publishBody($page, $publisher, '<p>Second</p>');

    Livewire::actingAs($publisher)->test(History::class, ['type' => 'page', 'id' => $page->id])
        ->assertSee('About us')
        ->assertSee($publisher->name)
        ->assertSee('body')
        ->assertSee(__('Live now'))
        ->assertSee(__('Before drafts were introduced'));
});

// ── Pictures come back with their version ──────────────────────────────────

test('restoring a banner version brings its picture back', function () {
    Storage::fake('public');
    $drafts = app(ContentDrafts::class);
    $publisher = contentPublisher();
    $banner = Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true]);
    $banner->addMedia(UploadedFile::fake()->image('first.jpg', 1500, 500))->toMediaCollection('image');

    $draft = $drafts->stage($banner->fresh(), $publisher, withFiles: true);
    $draft->addMedia(UploadedFile::fake()->image('second.jpg', 1500, 500))->toMediaCollection('image');
    $drafts->publish($banner, $publisher);

    expect($banner->fresh()->getFirstMedia('image')->file_name)->toBe('second.jpg');

    $first = $banner->versions()->get()->last();
    expect($first->getFirstMedia('image')?->file_name)->toBe('first.jpg');

    $drafts->restore($first, $publisher);
    expect($banner->fresh()->getFirstMedia('image')->file_name)->toBe('second.jpg'); // still a draft

    $drafts->publish($banner, $publisher);
    expect($banner->fresh()->getFirstMedia('image')->file_name)->toBe('first.jpg')
        ->and(ContentVersion::where('status', 'draft')->count())->toBe(0);
});
