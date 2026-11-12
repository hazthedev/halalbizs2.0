<?php

use App\Livewire\Admin\Content\HomeSections;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Store;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** Adds a section of a type on the admin screen and returns the screen with its editor open. */
function addedSection(string $type)
{
    return Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->set('addType', $type)
        ->call('addSection')
        ->assertSet('editingId', HomeSection::where('type', $type)->latest('id')->value('id'));
}

function appBlocks(): array
{
    return test()->getJson('/api/v1/home')->assertOk()->json('data.blocks');
}

// ── Item 14: short text notice ────────────────────────────────────────────

test('a short notice shows on web and app once published, in the shopper language with English as fallback', function () {
    addedSection('notice')
        ->set('noticeText.en', 'Free delivery over RM80 this week')
        ->set('noticeText.ms', 'Penghantaran percuma melebihi RM80')
        ->call('save')
        ->assertHasNoErrors();

    // Draft only so far.
    $this->get('/')->assertDontSee('Free delivery over RM80 this week');

    publishDrafts();

    $this->get('/')->assertSee('Free delivery over RM80 this week');

    expect(appBlocks())->toHaveCount(1)
        ->and(appBlocks()[0])->toMatchArray(['type' => 'notice', 'text' => 'Free delivery over RM80 this week']);

    expect($this->withHeaders(['Accept-Language' => 'ms'])->getJson('/api/v1/home')->json('data.blocks.0.text'))->toBe('Penghantaran percuma melebihi RM80')
        ->and($this->withHeaders(['Accept-Language' => 'vi'])->getJson('/api/v1/home')->json('data.blocks.0.text'))->toBe('Free delivery over RM80 this week');
});

test('a notice needs English text and stays short', function () {
    addedSection('notice')
        ->set('noticeText.en', '')
        ->call('save')
        ->assertHasErrors(['noticeText.en']);

    addedSection('notice')
        ->set('noticeText.en', str_repeat('a', 201))
        ->call('save')
        ->assertHasErrors(['noticeText.en']);
});

test('there can be more than one of each new type', function () {
    addedSection('notice');
    addedSection('notice');
    addedSection('picked_products');
    addedSection('picked_products');

    expect(HomeSection::where('type', 'notice')->count())->toBe(2)
        ->and(HomeSection::where('type', 'picked_products')->count())->toBe(2);
});

test('a layout holds at most 10 sections', function () {
    foreach (range(1, 10) as $i) {
        HomeSection::create(['type' => 'notice', 'payload' => ['text' => ['en' => "Notice $i"]], 'position' => $i, 'is_active' => true]);
    }

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->set('addType', 'notice')
        ->call('addSection');

    expect(HomeSection::count())->toBe(10);
});

// ── Item 14: hand-picked products ─────────────────────────────────────────

test('hand-picked products show in the picked order, and one that is no longer on sale drops out', function () {
    $first = Product::factory()->create(['name' => ['en' => 'Sambal Nyet']]);
    $second = Product::factory()->create(['name' => ['en' => 'Kurma Ajwa']]);
    $gone = Product::factory()->create(['name' => ['en' => 'Sold out thing']]);

    addedSection('picked_products')
        ->set('title.en', 'Staff picks')
        ->call('pick', $second->id)
        ->call('pick', $gone->id)
        ->call('pick', $first->id)
        ->call('pick', $first->id) // twice = once
        ->call('save')
        ->assertHasNoErrors();

    publishDrafts();
    $gone->delete();

    $block = appBlocks()[0];

    expect($block['type'])->toBe('picked_products')
        ->and($block['title'])->toBe('Staff picks')
        ->and(array_column($block['products'], 'id'))->toBe([$second->id, $first->id]);

    $this->get('/')->assertSeeInOrder(['Staff picks', 'Kurma Ajwa', 'Sambal Nyet'])->assertDontSee('Sold out thing');
});

test('the picker finds products by name and a pick can be taken off again', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Sambal Nyet']]);
    Product::factory()->create(['name' => ['en' => 'Kurma Ajwa']]);

    addedSection('picked_products')
        ->set('pickSearch', 'sambal')
        ->assertSee('Sambal Nyet')
        ->assertDontSee('Kurma Ajwa')
        ->call('pick', $product->id)
        ->assertSet('picked', [$product->id])
        ->call('unpick', $product->id)
        ->assertSet('picked', []);
});

// ── Item 14: hand-picked stores ───────────────────────────────────────────

test('hand-picked stores show on web and app; a store that is not approved never shows', function () {
    $store = Store::factory()->approved()->create(['name' => 'Kedai Mak Cik', 'slug' => 'kedai-mak-cik']);
    $pending = Store::factory()->create(['name' => 'Not yet open']);

    addedSection('picked_stores')
        ->set('title.en', 'Stores we love')
        ->call('pick', $store->id)
        ->call('pick', $pending->id)
        ->call('save')
        ->assertHasNoErrors();

    publishDrafts();

    $block = appBlocks()[0];

    expect($block['type'])->toBe('picked_stores')
        ->and($block['stores'])->toHaveCount(1)
        ->and($block['stores'][0])->toMatchArray(['id' => $store->id, 'slug' => 'kedai-mak-cik', 'name' => 'Kedai Mak Cik']);

    $this->get('/')->assertSee('Stores we love')->assertSee('Kedai Mak Cik')->assertSee('/s/kedai-mak-cik')->assertDontSee('Not yet open');
});

// ── Item 14: picture tiles ────────────────────────────────────────────────

test('picture tiles (2 to 4) show with their label and link once published', function () {
    Storage::fake('public');

    addedSection('picture_tiles')
        ->set('tileImages.1', UploadedFile::fake()->image('dates.jpg', 800, 800))
        ->set('tileLabels.1.en', 'Dates')
        ->set('tileLinks.1', '/search')
        ->set('tileImages.2', UploadedFile::fake()->image('gifts.jpg', 800, 800))
        ->set('tileLabels.2.en', 'Gifts')
        ->set('tileLabels.2.ms', 'Hadiah')
        ->call('save')
        ->assertHasNoErrors();

    // Draft only: nothing on the public home yet.
    expect(appBlocks())->toBe([]);

    publishDrafts();

    $tiles = appBlocks()[0]['tiles'];

    expect(appBlocks()[0]['type'])->toBe('picture_tiles')
        ->and($tiles)->toHaveCount(2)
        ->and($tiles[0]['label'])->toBe('Dates')
        ->and($tiles[0]['link_url'])->toBe('/search')
        ->and($tiles[0]['app_url'])->toBe('/search')
        ->and($tiles[0]['image'])->toContain('dates')
        ->and($tiles[1]['label'])->toBe('Gifts')
        ->and($tiles[1]['link_url'])->toBeNull();

    $this->get('/')->assertSee('Dates')->assertSee('Gifts')->assertSee('href="/search"', false);
});

test('picture tiles need at least two pictures, big enough, and a link on this site', function () {
    Storage::fake('public');

    addedSection('picture_tiles')
        ->set('tileImages.1', UploadedFile::fake()->image('one.jpg', 800, 800))
        ->call('save')
        ->assertHasErrors(['tileImages']);

    addedSection('picture_tiles')
        ->set('tileImages.1', UploadedFile::fake()->image('one.jpg', 800, 800))
        ->set('tileImages.2', UploadedFile::fake()->image('tiny.jpg', 100, 100))
        ->set('tileLinks.1', 'https://evil.test/x')
        ->call('save')
        ->assertHasErrors(['tileImages.2', 'tileLinks.1']);
});

test('a tile can be taken off, and its picture goes at publish', function () {
    Storage::fake('public');

    $screen = addedSection('picture_tiles')
        ->set('tileImages.1', UploadedFile::fake()->image('a.jpg', 800, 800))
        ->set('tileImages.2', UploadedFile::fake()->image('b.jpg', 800, 800))
        ->set('tileImages.3', UploadedFile::fake()->image('c.jpg', 800, 800))
        ->call('save');
    publishDrafts();

    $section = HomeSection::where('type', 'picture_tiles')->sole();
    expect(appBlocks()[0]['tiles'])->toHaveCount(3);

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->call('edit', $section->id)
        ->call('removeTile', 3)
        ->call('save')
        ->assertHasNoErrors();

    // Still three until published.
    expect(appBlocks()[0]['tiles'])->toHaveCount(3);

    publishDrafts();

    expect(appBlocks()[0]['tiles'])->toHaveCount(2)
        ->and($section->fresh()->getMedia('tile_3'))->toHaveCount(0);
});
