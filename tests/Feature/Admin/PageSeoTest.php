<?php

use App\Livewire\Admin\Content\Pages;
use App\Models\Page;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function seoPage(): Page
{
    return Page::create(['slug' => 'shipping', 'title' => ['en' => 'Shipping guide'], 'body' => ['en' => '<p>We ship <strong>nationwide</strong> from Kuala Lumpur within two working days.</p>'], 'is_active' => true]);
}

// ── Item 17: search engine title, description and share picture per page ───

test('a page gets its own search title, description and share picture once published', function () {
    Storage::fake('public');
    $page = seoPage();

    Livewire::actingAs(contentPublisher())->test(Pages::class)
        ->call('edit', $page->id)
        ->set('seoTitle.en', 'Shipping across Malaysia')
        ->set('seoDescription.en', 'How fast we ship and what it costs.')
        ->set('seoDescription.ms', 'Berapa cepat kami menghantar.')
        ->set('shareImage', UploadedFile::fake()->image('share.jpg', 1200, 630))
        ->call('save')
        ->assertHasNoErrors();

    // Still a draft: the public page keeps the plain tags.
    $this->get('/page/shipping')->assertDontSee('How fast we ship');

    publishDrafts();

    $html = $this->get('/page/shipping')->assertOk()->getContent();

    expect($html)->toContain('<title>Shipping across Malaysia · ')
        ->toContain('<meta name="description" content="How fast we ship and what it costs.">')
        ->toContain('<meta property="og:title" content="Shipping across Malaysia">')
        ->toContain('<meta property="og:description" content="How fast we ship and what it costs.">')
        ->toContain('<meta property="og:image" content="'.$page->fresh()->getFirstMediaUrl('share_image').'">')
        ->toContain('<link rel="canonical" href="'.route('page.show', 'shipping').'">');

    $this->withSession(['locale' => 'ms'])->get('/page/shipping')
        ->assertSee('<meta name="description" content="Berapa cepat kami menghantar.">', false);
});

test('a page without search fields falls back to its title and the start of its text', function () {
    seoPage();

    $html = $this->get('/page/shipping')->getContent();

    expect($html)->toContain('<meta name="description" content="We ship nationwide from Kuala Lumpur within two working days.">')
        ->toContain('<meta property="og:title" content="Shipping guide">')
        ->not->toContain('og:image');
});

test('search fields are length-checked and the share picture must be a wide enough picture', function () {
    $page = seoPage();

    Livewire::actingAs(contentPublisher())->test(Pages::class)
        ->call('edit', $page->id)
        ->set('seoTitle.en', str_repeat('a', 71))
        ->set('seoDescription.en', str_repeat('b', 201))
        ->set('shareImage', UploadedFile::fake()->image('small.jpg', 300, 200))
        ->call('save')
        ->assertHasErrors(['seoTitle.en', 'seoDescription.en', 'shareImage']);
});

test('the share picture can be removed again, as a draft', function () {
    Storage::fake('public');
    $page = seoPage();
    $page->addMedia(UploadedFile::fake()->image('share.jpg', 1200, 630))->toMediaCollection('share_image');

    Livewire::actingAs(contentPublisher())->test(Pages::class)->call('edit', $page->id)->call('removeShareImage');

    expect($page->fresh()->getMedia('share_image'))->toHaveCount(1);

    publishDrafts();

    expect($page->fresh()->getMedia('share_image'))->toHaveCount(0);
});

it('links a ticked custom page in the website footer once published, and never a system page by the tick', function () {
    $admin = contentPublisher();
    $page = Page::create(['slug' => 'careers', 'title' => ['en' => 'Careers at HalalBizs'], 'body' => ['en' => '<p>Join us.</p>'], 'is_active' => true]);

    Livewire::actingAs($admin)->test(Pages::class)
        ->call('edit', $page->id)
        ->set('showInFooter', true)
        ->call('save');

    // Draft only: the footer has no link yet.
    expect($this->get('/')->getContent())->not->toContain('/page/careers');

    publishDrafts();

    expect($this->get('/')->getContent())->toContain('/page/careers')->toContain('Careers at HalalBizs');

    // A system page keeps its fixed footer place; the tick is ignored.
    $about = Page::where('slug', 'about')->first() ?? Page::create(['slug' => 'about', 'title' => ['en' => 'About'], 'body' => ['en' => '<p>x</p>'], 'is_active' => true]);
    Livewire::actingAs($admin)->test(Pages::class)
        ->call('edit', $about->id)
        ->set('showInFooter', true)
        ->call('save');
    publishDrafts();

    expect($about->fresh()->show_in_footer)->toBeFalse();
});
