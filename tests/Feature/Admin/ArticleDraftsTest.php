<?php

use App\Livewire\Admin\Support\Articles;
use App\Models\HelpArticle;
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

function liveArticle(): HelpArticle
{
    return HelpArticle::create(['category' => 'buying', 'title' => ['en' => 'Old title'], 'body' => ['en' => '<p>Old words</p>'], 'position' => 0, 'is_active' => true]);
}

test('an article edit is a draft; web and API help keep the live words', function () {
    $article = liveArticle();

    Livewire::actingAs(draftEditor())->test(Articles::class)
        ->call('edit', $article->id)->set('title.en', 'New title')->set('body.en', '<p>New words</p>')->call('save')->assertHasNoErrors();

    expect($article->fresh()->getTranslation('title', 'en'))->toBe('Old title')->and($article->fresh()->draft)->not->toBeNull();

    $this->get('/help')->assertSee('Old title')->assertDontSee('New title');
    $this->get("/help/article/{$article->id}")->assertSee('Old words')->assertDontSee('New words');
    $this->getJson('/api/v1/help')->assertJsonPath('data.0.title', 'Old title');
    $this->getJson("/api/v1/help/{$article->id}")->assertJsonPath('data.body', '<p>Old words</p>');
});

test('?preview=1 shows drafted articles to the editor only and does not count a view', function () {
    $article = liveArticle();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(Articles::class)->call('edit', $article->id)->set('title.en', 'New title')->set('body.en', '<p>New words</p>')->call('save');
    Livewire::actingAs($editor)->test(Articles::class)->set('isActive', true)->call('create')->set('title.en', 'Brand new')->set('body.en', '<p>x</p>')->call('save');

    $this->actingAs($editor)->get('/help?preview=1')->assertSee('New title')->assertSee('Brand new');
    $this->actingAs($editor)->get("/help/article/{$article->id}?preview=1")->assertSee('New words');
    $this->actingAs($editor)->getJson('/api/v1/help?preview=1')->assertJsonPath('data.0.title', 'New title');
    $this->actingAs($editor)->getJson("/api/v1/help/{$article->id}?preview=1")->assertJsonPath('data.body', '<p>New words</p>');
    expect($article->fresh()->views)->toBe(0);

    auth()->logout();
    $this->getJson('/api/v1/help')->assertJsonCount(1, 'data');
    $this->actingAs(User::factory()->create())->get('/help?preview=1')->assertSee('Old title')->assertDontSee('Brand new');
});

test('a publisher publishes; the new article goes live, delete needs publish rights', function () {
    $article = liveArticle();
    $editor = draftEditor();

    Livewire::actingAs($editor)->test(Articles::class)->call('edit', $article->id)->set('title.en', 'New title')->call('save');
    Livewire::actingAs($editor)->test(Articles::class)->call('toggleActive', $article->id);
    Livewire::actingAs($editor)->test(Articles::class)->call('delete', $article->id)->assertForbidden();

    Livewire::actingAs(draftPublisher())->test(Articles::class)->assertSee('Draft')->call('publishDraft', 'article', $article->id);

    expect($article->fresh()->getTranslation('title', 'en'))->toBe('New title')->and($article->fresh()->is_active)->toBeFalse();
    $this->getJson('/api/v1/help')->assertJsonCount(0, 'data');
});
