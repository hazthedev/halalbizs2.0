<?php

use App\Livewire\Admin\Content\Banners;
use App\Models\Banner;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// Own helpers (not the ones in ContentDraftsTest) so this file also runs alone.
function bannerEditor(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions(['cms.manage']);

    return $user->fresh();
}

function bannerPublisher(): User
{
    return makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
}

function liveBanner(array $extra = []): Banner
{
    return Banner::create(['title' => ['en' => 'Sale'], 'position' => 1, 'is_active' => true, ...$extra]);
}

test('an editor saves a banner change with a new picture; the live banner and picture stay', function () {
    Storage::fake('public');
    $banner = liveBanner();
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 1500, 500))->toMediaCollection('image');

    Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->call('edit', $banner->id)
        ->set('title.en', 'New sale')
        ->set('image', UploadedFile::fake()->image('new.jpg', 1500, 500))
        ->call('save')
        ->assertHasNoErrors();

    $banner = $banner->fresh();
    expect($banner->getTranslation('title', 'en'))->toBe('Sale')
        ->and($banner->getFirstMedia('image')->file_name)->toBe('old.jpg')
        ->and($banner->draft)->not->toBeNull()
        ->and($banner->draft->getFirstMedia('image')->file_name)->toBe('new.jpg');
});

test('a publisher publishes the draft and the new picture goes live', function () {
    Storage::fake('public');
    $banner = liveBanner();
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 1500, 500))->toMediaCollection('image');

    Livewire::actingAs(bannerPublisher())->test(Banners::class)
        ->call('edit', $banner->id)
        ->set('title.en', 'New sale')
        ->set('image', UploadedFile::fake()->image('new.jpg', 1500, 500))
        ->call('save')
        ->call('publishDraft', 'banner', $banner->id);

    $banner = $banner->fresh();
    expect($banner->getTranslation('title', 'en'))->toBe('New sale')
        ->and($banner->getFirstMedia('image')->file_name)->toBe('new.jpg')
        ->and($banner->draft)->toBeNull();
});

test('editing a banner with an open draft continues the draft', function () {
    $banner = liveBanner();

    $page = Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->call('edit', $banner->id)->set('title.en', 'Draft title')->call('save')
        ->call('edit', $banner->id);

    expect($page->get('title')['en'])->toBe('Draft title');
});

test('a new banner is hidden until published', function () {
    Storage::fake('public');

    Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Fresh')
        ->set('image', UploadedFile::fake()->image('n.jpg', 1500, 500))
        ->call('save')
        ->assertHasNoErrors();

    $banner = Banner::sole();
    expect($banner->is_active)->toBeFalse()
        ->and($banner->draft->payload['is_active'])->toBeTruthy();

    Livewire::actingAs(bannerPublisher())->test(Banners::class)->call('publishDraft', 'banner', $banner->id);

    expect($banner->fresh()->is_active)->toBeTrue()
        ->and($banner->fresh()->getFirstMedia('image')->file_name)->toBe('n.jpg');
});

test('on/off is a draft', function () {
    $banner = liveBanner();

    Livewire::actingAs(bannerEditor())->test(Banners::class)->call('toggleActive', $banner->id);

    expect($banner->fresh()->is_active)->toBeTrue()
        ->and($banner->fresh()->draft->payload['is_active'])->toBeFalsy();
});

test('reorder is a draft and stages only banners that move', function () {
    $a = liveBanner(['position' => 0]);
    $b = liveBanner(['title' => ['en' => 'B'], 'position' => 1]);
    $c = liveBanner(['title' => ['en' => 'C'], 'position' => 2]);

    Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->call('move', $b->id, 1);

    expect($b->fresh()->position)->toBe(1)
        ->and($b->fresh()->draft->payload['position'])->toBe(2)
        ->and($c->fresh()->draft->payload['position'])->toBe(1)
        ->and($a->fresh()->draft)->toBeNull();

    // a second move reads the draft positions
    Livewire::actingAs(bannerEditor())->test(Banners::class)->call('move', $b->id, 1);
    expect($b->fresh()->draft->payload['position'])->toBe(2);
});

test('removing the video is staged, the live video stays until publish', function () {
    Storage::fake('public');
    $banner = liveBanner();
    $banner->addMedia(UploadedFile::fake()->create('v.mp4', 100, 'video/mp4'))->toMediaCollection('video');

    Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->call('edit', $banner->id)->call('removeVideo');

    expect($banner->fresh()->getFirstMedia('video'))->not->toBeNull();

    Livewire::actingAs(bannerPublisher())->test(Banners::class)->call('publishDraft', 'banner', $banner->id);
    expect($banner->fresh()->getFirstMedia('video'))->toBeNull();
});

test('an editor cannot delete; a publisher can', function () {
    $banner = liveBanner();

    Livewire::actingAs(bannerEditor())->test(Banners::class)->call('delete', $banner->id)->assertForbidden();
    expect(Banner::count())->toBe(1);

    Livewire::actingAs(bannerPublisher())->test(Banners::class)->call('delete', $banner->id);
    expect(Banner::count())->toBe(0);
});

test('the list shows Draft and publish buttons only to a publisher', function () {
    $banner = liveBanner();
    Livewire::actingAs(bannerEditor())->test(Banners::class)->call('toggleActive', $banner->id);

    Livewire::actingAs(bannerEditor())->test(Banners::class)
        ->assertSee('Draft')->assertDontSee('publishDraft');
    Livewire::actingAs(bannerPublisher())->test(Banners::class)
        ->assertSee("publishDraft('banner', {$banner->id})", false)
        ->assertSee("discardDraft('banner', {$banner->id})", false);
});
