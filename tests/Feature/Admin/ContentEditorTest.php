<?php

use App\Enums\HelpCategory;
use App\Livewire\Admin\Content\Pages;
use App\Livewire\Admin\Support\Articles;
use App\Models\HelpArticle;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// CMS item 6: pages and help articles are written with editor buttons, not
// typed HTML. The editor hands the same HTML to the same server-side cleaner.

function editorAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($user);

    return $user;
}

test('the page form offers an editor per language instead of an HTML box', function () {
    $page = Page::create(['slug' => 'about', 'title' => ['en' => 'About'], 'body' => ['en' => '<p>Hi</p>'], 'is_active' => true]);

    Livewire::actingAs(editorAdmin())->test(Pages::class)
        ->call('edit', $page->id)
        ->assertSeeHtml('data-rich-editor="body.en"')
        ->assertSeeHtml('data-rich-editor="body.ms"')
        ->assertSeeHtml('data-rich-editor="body.vi"')
        ->assertSeeHtml('aria-label="Heading"')
        ->assertSeeHtml('aria-label="Link"')
        ->assertDontSeeHtml('<textarea')
        ->assertDontSee('allowed tags');
});

test('the help article form offers the same editor', function () {
    $article = HelpArticle::create(['category' => HelpCategory::Buying, 'title' => ['en' => 'Track'], 'body' => ['en' => '<p>x</p>'], 'position' => 1, 'is_active' => true]);

    Livewire::actingAs(editorAdmin())->test(Articles::class)
        ->call('edit', $article->id)
        ->assertSeeHtml('data-rich-editor="body.en"')
        ->assertSeeHtml('data-rich-editor="body.ms"')
        ->assertSeeHtml('data-rich-editor="body.vi"')
        ->assertDontSeeHtml('<textarea')
        ->assertDontSee('allowed tags');
});

test('what the editor produces is kept, and anything unsafe is still removed', function () {
    $page = Page::create(['slug' => 'about', 'title' => ['en' => 'About'], 'body' => ['en' => '<p>Hi</p>'], 'is_active' => true]);

    // The exact shape Tiptap emits: list items wrap their text in <p>, links carry target/rel.
    $fromEditor = '<h2>Our story</h2><p><strong>Bold</strong> and <em>italic</em> with a '
        .'<a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/halal">link</a>.</p>'
        .'<ul><li><p>One</p></li></ul><h3>Small</h3><ol><li><p>First</p></li></ol>'
        .'<p><a href="javascript:alert(1)">bad</a><script>alert(1)</script></p>';

    Livewire::actingAs(editorAdmin())->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', $fromEditor)
        ->call('save')
        ->assertHasNoErrors();

    $saved = $page->fresh()->getTranslation('body', 'en');

    expect($saved)->toContain('<h2>Our story</h2>', '<strong>Bold</strong>', '<em>italic</em>', '<a href="https://example.com/halal">link</a>', '<ul><li><p>One</p></li></ul>', '<h3>Small</h3>', '<ol><li><p>First</p></li></ol>')
        ->not->toContain('javascript:', '<script', 'alert(1)', 'target=');
});
