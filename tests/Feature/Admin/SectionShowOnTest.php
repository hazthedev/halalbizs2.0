<?php

use App\Livewire\Admin\Content\HomeSections;
use App\Models\Category;
use App\Models\HomeSection;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Category::factory()->create(['parent_id' => null, 'is_active' => true]);
});

function showOnSection(string $showOn = 'both'): HomeSection
{
    return HomeSection::create(['type' => 'category_grid', 'title' => ['en' => 'Shelf seven'], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true, 'show_on' => $showOn]);
}

function appTypes(): array
{
    return array_column(test()->getJson('/api/v1/home')->json('data.blocks'), 'type');
}

test('a new section shows on both web and app by default', function () {
    $section = HomeSection::create(['type' => 'category_grid', 'title' => ['en' => 'Shelf seven'], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true]);

    expect($section->fresh()->show_on)->toBe('both');
    $this->get('/')->assertSee('Shelf seven');
    expect(appTypes())->toBe(['category_grid']);
});

test('app only is absent from the web home and present in the app', function () {
    showOnSection('app');

    $this->get('/')->assertDontSee('Shelf seven');
    expect(appTypes())->toBe(['category_grid']);
});

test('web only is present on the web home and absent from the app', function () {
    showOnSection('web');

    $this->get('/')->assertSee('Shelf seven');
    expect(appTypes())->toBe([]);
});

test('the choice is a draft until published, shows in preview, then goes live', function () {
    $section = showOnSection();
    $editor = contentEditor();

    Livewire::actingAs($editor)->test(HomeSections::class)
        ->call('edit', $section->id)->set('showOn', 'app')->call('save')->assertHasNoErrors();

    expect($section->fresh()->show_on)->toBe('both');
    $this->get('/')->assertSee('Shelf seven');
    $this->actingAs($editor)->get('/?preview=1')->assertDontSee('Shelf seven');

    publishDrafts();
    auth()->logout();

    expect($section->fresh()->show_on)->toBe('app');
    $this->get('/')->assertDontSee('Shelf seven');
    expect(appTypes())->toBe(['category_grid']);
});

test('show on only accepts both, web or app', function () {
    $section = showOnSection();

    Livewire::actingAs(contentEditor())->test(HomeSections::class)
        ->call('edit', $section->id)->set('showOn', 'tv')->call('save')->assertHasErrors('showOn');
});

test('the list badges a section that is not on both', function () {
    showOnSection('app');

    Livewire::actingAs(contentEditor())->test(HomeSections::class)->assertSee('App only')->assertDontSee('Web only');
});
