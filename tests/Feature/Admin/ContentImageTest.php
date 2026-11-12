<?php

use App\Livewire\Admin\Content\Banners;
use App\Livewire\Admin\Content\Theme;
use App\Models\Banner;
use App\Models\HomeSection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// CMS item 8: a wrong-shaped or tiny picture is refused at upload, and the
// site makes its own small fast copy, so an admin cannot slow the home page.

function imageAdmin(): User
{
    test()->seed(RoleSeeder::class);
    Storage::fake('public');

    $user = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($user);

    return $user;
}

test('a banner picture must be wide (about 3 to 1) and at least 1200 pixels across', function (int $width, int $height, bool $accepted) {
    $form = Livewire::actingAs(imageAdmin())->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Raya Sale')
        ->set('image', UploadedFile::fake()->image('banner.jpg', $width, $height))
        ->call('save');

    $accepted ? $form->assertHasNoErrors() : $form->assertHasErrors('image');

    expect(Banner::count())->toBe($accepted ? 1 : 0);
})->with([
    'square' => [1200, 1200, false],
    'too small' => [600, 200, false],
    'tall' => [1200, 800, false],
    '3 to 1' => [1500, 500, true],
    'the old hint size' => [1280, 400, true],
]);

test('the Vietnamese banner picture follows the same shape rule', function () {
    Livewire::actingAs(imageAdmin())->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Raya Sale')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1500, 500))
        ->set('imageVi', UploadedFile::fake()->image('banner-vi.jpg', 500, 500))
        ->call('save')
        ->assertHasErrors('imageVi');
});

test('the occasion hero picture must be wide and at least 1200 pixels across', function () {
    $admin = imageAdmin();

    Livewire::actingAs($admin)->test(Theme::class)
        ->set('heroImage', UploadedFile::fake()->image('hero.jpg', 500, 500))
        ->call('save')
        ->assertHasErrors('heroImage');

    Livewire::actingAs($admin)->test(Theme::class)
        ->set('heroImage', UploadedFile::fake()->image('hero.jpg', 1600, 600))
        ->call('save')
        ->assertHasNoErrors();
});

test('a saved banner gets a small fast copy and the home page offers it to phones', function () {
    Livewire::actingAs(imageAdmin())->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Raya Sale')
        ->set('image', UploadedFile::fake()->image('banner.jpg', 1800, 600))
        ->call('save')
        ->assertHasNoErrors()
        ->call('publishDraft', 'banner', Banner::sole()->id);

    $media = Banner::sole()->getFirstMedia('image');

    expect($media->hasGeneratedConversion('small'))->toBeTrue()
        ->and($media->getUrl('small'))->toEndWith('.webp');

    HomeSection::create(['type' => 'banner', 'title' => ['en' => 'Promotions'], 'payload' => [], 'position' => 1, 'is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee($media->getUrl('small').' 800w, '.$media->getUrl('card').' 1600w', false)
        ->assertSee('fetchpriority="high"', false);
});

test('a banner saved before small copies existed still shows its picture', function () {
    imageAdmin();
    $banner = Banner::create(['title' => ['en' => 'Old'], 'position' => 1, 'is_active' => true]);
    $banner->addMedia(UploadedFile::fake()->image('old.jpg', 1500, 500))->toMediaCollection('image');
    $media = $banner->fresh()->getFirstMedia('image'); // fresh: the conversions ran after the upload returned
    $media->markAsConversionNotGenerated('small');
    $media->save();
    HomeSection::create(['type' => 'banner', 'title' => ['en' => 'Promotions'], 'payload' => [], 'position' => 1, 'is_active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee($media->getUrl('card'), false)
        ->assertDontSee('800w', false);
});
