<?php

use App\Livewire\Admin\Content\HomeSections;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\ScheduledLayout;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Category::factory()->create(['parent_id' => null, 'is_active' => true]);
    $this->travelTo('2027-02-01 00:00:00');
});

function everydaySection(string $title = 'Everyday shelf'): HomeSection
{
    return HomeSection::create(['type' => 'category_grid', 'title' => ['en' => $title], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true]);
}

/** A layout from 10 Feb to 10 Mar 2027 (UTC) with one live section. */
function ramadanLayout(bool $withSection = true): ScheduledLayout
{
    $layout = ScheduledLayout::create(['name' => 'Ramadan', 'starts_at' => '2027-02-10 00:00:00', 'ends_at' => '2027-03-10 00:00:00']);

    if ($withSection) {
        $section = new HomeSection(['type' => 'category_grid', 'title' => ['en' => 'Ramadan shelf'], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true]);
        $section->scheduled_layout_id = $layout->id;
        $section->save();
    }

    return $layout;
}

function appTitles(string $query = ''): array
{
    return array_column(test()->getJson('/api/v1/home'.$query)->json('data.blocks'), 'title');
}

// ── Item 12: the home switches to a scheduled layout and back by itself ───

test('the home shows the everyday layout before the start, the scheduled one inside the window, and the everyday one again after the end', function () {
    everydaySection();
    ramadanLayout();

    $this->get('/')->assertSee('Everyday shelf')->assertDontSee('Ramadan shelf');

    $this->travelTo('2027-02-10 00:00:01');
    $this->get('/')->assertSee('Ramadan shelf')->assertDontSee('Everyday shelf');

    $this->travelTo('2027-03-10 00:00:00');
    $this->get('/')->assertSee('Everyday shelf')->assertDontSee('Ramadan shelf');
});

test('the app switches at the start and the end without anybody clearing the cache', function () {
    everydaySection();
    ramadanLayout();

    // One second before the start: this answer is now cached.
    $this->travelTo('2027-02-09 23:59:59');
    expect(appTitles())->toBe(['Everyday shelf']);

    $this->travelTo('2027-02-10 00:00:02');
    expect(appTitles())->toBe(['Ramadan shelf']);

    $this->travelTo('2027-03-09 23:59:59');
    expect(appTitles())->toBe(['Ramadan shelf']);

    $this->travelTo('2027-03-10 00:00:02');
    expect(appTitles())->toBe(['Everyday shelf']);
});

test('a scheduled layout with nothing live in it never blanks the home', function () {
    everydaySection();
    ramadanLayout(withSection: false);

    $this->travelTo('2027-02-15 00:00:00');

    $this->get('/')->assertSee('Everyday shelf');
    expect(appTitles())->toBe(['Everyday shelf']);
});

test('when two layouts overlap the one that started last wins', function () {
    everydaySection();
    ramadanLayout();
    $raya = ScheduledLayout::create(['name' => 'Raya week', 'starts_at' => '2027-03-05 00:00:00', 'ends_at' => '2027-03-20 00:00:00']);
    $section = new HomeSection(['type' => 'category_grid', 'title' => ['en' => 'Raya shelf'], 'payload' => ['limit' => 8], 'position' => 0, 'is_active' => true]);
    $section->scheduled_layout_id = $raya->id;
    $section->save();

    $this->travelTo('2027-03-06 00:00:00');

    expect(appTitles())->toBe(['Raya shelf']);
});

test('staff can preview a scheduled layout before it starts; a shopper adding the same address sees the everyday home', function () {
    everydaySection();
    $layout = ramadanLayout();

    $this->get('/?preview=1&layout='.$layout->id)->assertSee('Everyday shelf')->assertDontSee('Ramadan shelf');

    $this->actingAs(contentEditor())->get('/?preview=1&layout='.$layout->id)->assertSee('Ramadan shelf')->assertDontSee('Everyday shelf');
});

// ── Admin: Home sections screen ───────────────────────────────────────────

test('a publisher makes a scheduled layout that starts as a copy of the everyday one', function () {
    everydaySection();

    $screen = Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->set('layoutName', 'Ramadan')
        ->set('layoutStarts', '2027-02-10T08:00')
        ->set('layoutEnds', '2027-03-10T08:00')
        ->call('saveLayout')
        ->assertHasNoErrors();

    $layout = ScheduledLayout::sole();

    // Typed in Malaysian time (UTC+8), stored in UTC.
    expect($layout->name)->toBe('Ramadan')
        ->and($layout->starts_at->toDateTimeString())->toBe('2027-02-10 00:00:00')
        ->and($layout->ends_at->toDateTimeString())->toBe('2027-03-10 00:00:00')
        ->and($screen->get('layoutId'))->toBe($layout->id);

    $copy = HomeSection::where('scheduled_layout_id', $layout->id)->sole();
    expect($copy->getTranslation('title', 'en'))->toBe('Everyday shelf')->and($copy->is_active)->toBeTrue();

    // The everyday layout is untouched.
    expect(HomeSection::whereNull('scheduled_layout_id')->count())->toBe(1);
});

test('a layout must have a name and end after it starts', function () {
    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->set('layoutName', '')
        ->set('layoutStarts', '2027-03-10T08:00')
        ->set('layoutEnds', '2027-02-10T08:00')
        ->call('saveLayout')
        ->assertHasErrors(['layoutName', 'layoutEnds']);

    expect(ScheduledLayout::count())->toBe(0);
});

test('an editor cannot make, change or delete a layout', function () {
    $layout = ramadanLayout();

    Livewire::actingAs(contentEditor())->test(HomeSections::class)
        ->set('layoutName', 'Sale')->set('layoutStarts', '2027-02-10T08:00')->set('layoutEnds', '2027-03-10T08:00')
        ->call('saveLayout')
        ->assertForbidden();

    Livewire::actingAs(contentEditor())->test(HomeSections::class)
        ->call('deleteLayout', $layout->id)
        ->assertForbidden();

    expect(ScheduledLayout::count())->toBe(1);
});

test('a publisher changes the dates of a layout and deletes it with its sections', function () {
    everydaySection();
    $layout = ramadanLayout();

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->call('chooseLayout', $layout->id)
        ->call('editLayout')
        ->assertSet('layoutName', 'Ramadan')
        ->assertSet('layoutStarts', '2027-02-10T08:00')
        ->set('layoutEnds', '2027-03-12T08:00')
        ->call('saveLayout')
        ->assertHasNoErrors();

    expect($layout->fresh()->ends_at->toDateTimeString())->toBe('2027-03-12 00:00:00')
        ->and(ScheduledLayout::count())->toBe(1);

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->call('deleteLayout', $layout->id);

    expect(ScheduledLayout::count())->toBe(0)
        ->and(HomeSection::pluck('scheduled_layout_id')->all())->toBe([null]);
});

test('the screen lists and adds sections for the chosen layout only', function () {
    everydaySection();
    $layout = ramadanLayout();

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->assertSee('Everyday shelf')->assertDontSee('Ramadan shelf')
        ->call('chooseLayout', $layout->id)
        ->assertSee('Ramadan shelf')->assertDontSee('Everyday shelf')
        ->set('addType', 'product_grid')
        ->call('addSection');

    $added = HomeSection::where('type', 'product_grid')->sole();
    expect($added->scheduled_layout_id)->toBe($layout->id);

    // Publishing the new section keeps it in its layout.
    publishDrafts();
    expect($added->fresh()->scheduled_layout_id)->toBe($layout->id)->and($added->fresh()->is_active)->toBeTrue();
});

test('the layout selector accepts a browser value and switches back to everyday', function () {
    everydaySection();
    $layout = ramadanLayout();

    Livewire::actingAs(contentPublisher())->test(HomeSections::class)
        ->assertSeeHtml('wire:change="chooseLayout($event.target.value === \'\' ? null : $event.target.value)"')
        ->call('chooseLayout', (string) $layout->id)
        ->assertSet('layoutId', $layout->id)
        ->assertSee('Ramadan shelf')->assertDontSee('Everyday shelf')
        ->call('chooseLayout', null)
        ->assertSet('layoutId', null)
        ->assertSee('Everyday shelf')->assertDontSee('Ramadan shelf');
});
