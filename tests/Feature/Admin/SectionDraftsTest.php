<?php

use App\Livewire\Admin\Content\HomeSections;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

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

function draftSections(): array
{
    Category::factory()->create(['parent_id' => null, 'is_active' => true]);

    return [
        HomeSection::create(['type' => 'category_grid', 'title' => ['en' => 'Old shelf'], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true]),
        HomeSection::create(['type' => 'recommended', 'title' => ['en' => 'For you'], 'payload' => null, 'position' => 1, 'is_active' => true]),
    ];
}

test('a section edit and a reorder are drafts; live web and API stay as they were', function () {
    [$grid, $recommended] = draftSections();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(HomeSections::class)
        ->call('edit', $grid->id)->set('title.en', 'New shelf')->call('save')->assertHasNoErrors()
        ->call('move', $grid->id, 1);

    expect($grid->fresh()->getTranslation('title', 'en'))->toBe('Old shelf')
        ->and($grid->fresh()->position)->toBe(0)
        ->and($grid->fresh()->draft)->not->toBeNull();

    $this->get('/')->assertSee('Old shelf')->assertDontSee('New shelf');
    expect(array_column($this->getJson('/api/v1/home')->json('data.blocks'), 'type'))->toBe(['category_grid', 'recommended']);
});

test('?preview=1 shows the drafted sections to the editor only, web and API', function () {
    [$grid, $recommended] = draftSections();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(HomeSections::class)
        ->call('edit', $grid->id)->set('title.en', 'New shelf')->call('save')
        ->call('move', $grid->id, 1);

    $this->actingAs($editor)->get('/?preview=1')->assertSee('New shelf');
    $blocks = $this->actingAs($editor)->getJson('/api/v1/home?preview=1')->json('data.blocks');
    expect(array_column($blocks, 'type'))->toBe(['recommended', 'category_grid'])
        ->and($blocks[1]['title'])->toBe('New shelf');

    // the preview did not poison the shoppers' cache
    auth()->logout();
    expect($this->getJson('/api/v1/home')->json('data.blocks.0.type'))->toBe('category_grid');

    $this->actingAs(User::factory()->create())->get('/?preview=1')->assertSee('Old shelf')->assertDontSee('New shelf');
    $this->actingAs(User::factory()->create())->getJson('/api/v1/home?preview=1')->assertJsonPath('data.blocks.0.type', 'category_grid');
});

test('a publisher publishes the section drafts; live and the app cache are fresh', function () {
    [$grid, $recommended] = draftSections();

    Livewire::actingAs(draftEditor())->test(HomeSections::class)
        ->call('edit', $grid->id)->set('title.en', 'New shelf')->call('save')
        ->call('move', $grid->id, 1);

    $this->getJson('/api/v1/home')->assertOk(); // warm the cache

    Livewire::actingAs(draftPublisher())->test(HomeSections::class)->call('publishAllDrafts');

    expect($grid->fresh()->draft)->toBeNull()->and($recommended->fresh()->draft)->toBeNull();
    $blocks = $this->getJson('/api/v1/home')->json('data.blocks');
    expect(array_column($blocks, 'type'))->toBe(['recommended', 'category_grid'])
        ->and($blocks[1]['title'])->toBe('New shelf');
});

test('a new section is stored hidden until published; toggling is a draft; delete needs publish rights', function () {
    [$grid] = draftSections();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(HomeSections::class)->set('addType', 'product_grid')->call('addSection');

    $new = HomeSection::where('type', 'product_grid')->firstOrFail();
    expect($new->is_active)->toBeFalse()->and($new->draft)->not->toBeNull();

    Livewire::actingAs($editor)->test(HomeSections::class)->call('toggleActive', $grid->id);
    expect($grid->fresh()->is_active)->toBeTrue()->and($grid->fresh()->draft)->not->toBeNull();

    Livewire::actingAs($editor)->test(HomeSections::class)->call('delete', $grid->id)->assertForbidden();
    Livewire::actingAs(draftPublisher())->test(HomeSections::class)->call('publishDraft', 'section', $new->id);
    expect($new->fresh()->is_active)->toBeTrue();
});

test('the list shows the draft badge and the publish buttons only to a publisher', function () {
    [$grid] = draftSections();

    Livewire::actingAs(draftEditor())->test(HomeSections::class)->call('edit', $grid->id)->set('title.en', 'New shelf')->call('save')
        ->assertSee('Draft')->assertDontSee('Publish all');
    Livewire::actingAs(draftPublisher())->test(HomeSections::class)->assertSee('Draft')->assertSee('Publish all')->assertSee('Throw away');
});
