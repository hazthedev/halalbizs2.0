<?php

/**
 * QA pass — CMS pages, end to end.
 *
 * The question this file answers is not "does save() run" but "does an admin's
 * edit reach the public page, and can any body an admin can type break it".
 * The body is admin-authored HTML rendered through {!! !!} into the storefront
 * layout (static-page.blade.php:15), so it is simultaneously a feature (rich
 * text must survive) and an injection surface (C1/C7 both landed here).
 *
 * Every assertion is on the RENDERED public response or a database row.
 */

use App\Livewire\Admin\Content\Pages;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

function cmsAdmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions(['cms.manage']);

    return $user->fresh();
}

function cmsPage(string $slug = 'qa-page', bool $active = true): Page
{
    return Page::create([
        'slug' => $slug,
        'title' => ['en' => 'QA Page'],
        'body' => ['en' => '<p>original body</p>'],
        'is_active' => $active,
    ]);
}

/** Save a body through the real admin component and return the public HTML. */
function cmsSaveAndFetch(Page $page, string $body): string
{
    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', $body)
        ->call('save')
        ->assertHasNoErrors();

    return test()->get('/page/'.$page->slug)->getContent();
}

// ── Does an edit take effect? ────────────────────────────────────────────

it('publishes an edited body to the public page', function () {
    $page = cmsPage();

    expect(test()->get('/page/qa-page')->getContent())->toContain('original body');

    $html = cmsSaveAndFetch($page, '<p>PUBLISHED-MARKER-42</p>');

    expect($html)->toContain('PUBLISHED-MARKER-42')
        ->and($html)->not->toContain('original body');
});

it('renders the Malay body under the ms locale and falls back to en without one', function () {
    $page = cmsPage();

    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', '<p>ENGLISH-BODY</p>')
        ->set('body.ms', '<p>BADAN-MELAYU</p>')
        ->call('save')
        ->assertHasNoErrors();

    // The locale has to come from the SESSION — SetLocale middleware resolves it
    // per request and overwrites anything app()->setLocale() set beforehand.
    expect(test()->withSession(['locale' => 'ms'])->get('/page/qa-page')->getContent())
        ->toContain('BADAN-MELAYU');

    // Clearing the ms body must fall back, not render an empty page.
    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.ms', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(test()->withSession(['locale' => 'ms'])->get('/page/qa-page')->getContent())
        ->toContain('ENGLISH-BODY');
});

// ── Publish state ────────────────────────────────────────────────────────

it('takes an unpublished page off the storefront', function () {
    $page = cmsPage();
    test()->get('/page/qa-page')->assertOk();

    Livewire::actingAs(cmsAdmin())->test(Pages::class)->call('toggleActive', $page->id);

    expect($page->fresh()->is_active)->toBeFalse();
    test()->get('/page/qa-page')->assertNotFound();
});

it('refuses to unpublish terms and privacy', function (string $slug) {
    $page = cmsPage($slug);

    Livewire::actingAs(cmsAdmin())->test(Pages::class)->call('toggleActive', $page->id);

    expect($page->fresh()->is_active)->toBeTrue();
    test()->get('/page/'.$slug)->assertOk();
})->with(['terms', 'privacy']);

// ── System pages ─────────────────────────────────────────────────────────

it('keeps a system page slug locked even when the form posts a new one', function () {
    $page = cmsPage('terms');

    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('slug', 'hijacked')
        ->set('body.en', '<p>still terms</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($page->fresh()->slug)->toBe('terms');
    test()->get('/page/terms')->assertOk();
    test()->get('/page/hijacked')->assertNotFound();
});

it('refuses to delete a system page', function () {
    $page = cmsPage('about');

    Livewire::actingAs(cmsAdmin())->test(Pages::class)->call('delete', $page->id);

    expect(Page::find($page->id))->not->toBeNull();
});

it('moves a custom page when its slug changes', function () {
    $page = cmsPage('old-slug');

    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('slug', 'new-slug')
        ->call('save')
        ->assertHasNoErrors();

    test()->get('/page/old-slug')->assertNotFound();
    test()->get('/page/new-slug')->assertOk();
});

// ── Legitimate rich text must SURVIVE ────────────────────────────────────

it('keeps the formatting a content editor actually uses', function () {
    $html = cmsSaveAndFetch(cmsPage(), <<<'HTML'
        <h2>Heading two</h2>
        <h3>Heading three</h3>
        <p>A <strong>bold</strong> and <em>italic</em> line.</p>
        <ul><li>bullet one</li></ul>
        <ol><li>number one</li></ol>
        <p><a href="https://example.com">external</a> and <a href="/help">internal</a></p>
        HTML);

    expect($html)->toContain('<h2>Heading two</h2>')
        ->and($html)->toContain('<h3>Heading three</h3>')
        ->and($html)->toContain('<strong>bold</strong>')
        ->and($html)->toContain('<em>italic</em>')
        ->and($html)->toContain('<li>bullet one</li>')
        ->and($html)->toContain('<li>number one</li>')
        ->and($html)->toContain('href="https://example.com"')
        ->and($html)->toContain('href="/help"');
});

// ── Can a body break the PAGE? ───────────────────────────────────────────

/**
 * The body is injected raw inside the layout. An unbalanced tag would swallow
 * or escape the container, so the footer is the canary: if it survives, the
 * document structure did.
 */
it('survives unbalanced and structural markup without breaking the layout', function (string $label, string $body) {
    $html = cmsSaveAndFetch(cmsPage(), $body);

    expect($html)->toContain('CANARY-TEXT')
        ->and(substr_count($html, '<footer'))->toBe(1)
        // the body must not leak an extra container that unbalances the page
        ->and(substr_count($html, '<html'))->toBe(1);
})->with([
    ['unclosed div', '<div><p>CANARY-TEXT'],
    ['stray closing div', '</div><p>CANARY-TEXT</p></div></div>'],
    ['unclosed paragraph', '<p>CANARY-TEXT'],
    ['table markup', '<table><tr><td>CANARY-TEXT</td></tr></table>'],
    ['nested layout escape', '</div></main><p>CANARY-TEXT</p>'],
    ['html comment', '<!-- comment --><p>CANARY-TEXT</p>'],
    ['deep nesting', str_repeat('<p>', 40).'CANARY-TEXT'.str_repeat('</p>', 40)],
]);

// ── Injection (C1 / C7 regression) ───────────────────────────────────────

it('strips every attribute except a safe href', function () {
    $html = cmsSaveAndFetch(cmsPage(), <<<'HTML'
        <p onclick="alert(1)" style="position:fixed" id="x">KEEP-ME</p>
        <a href="javascript:alert(1)">bad link</a>
        <a href="https://ok.test" onmouseover="alert(1)">good link</a>
        HTML);

    expect($html)->toContain('KEEP-ME')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('onmouseover')
        ->and($html)->not->toContain('position:fixed')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->toContain('href="https://ok.test"');
});

/**
 * Assert on the STORED body, not the whole page: the stored string is exactly
 * what static-page.blade.php:15 emits through {!! !!}, whereas the full page
 * always carries Livewire and Vite <script> tags of its own.
 */
it('does not hand a raw-text element body back as live markup', function (string $payload) {
    $page = cmsPage();
    $html = cmsSaveAndFetch($page, $payload.'<p>CANARY-TEXT</p>');
    $stored = $page->fresh()->getTranslation('body', 'en');

    expect($html)->toContain('CANARY-TEXT')
        ->and($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('alert(1)')
        ->and($stored)->not->toContain('onerror')
        ->and($stored)->not->toContain('<img')
        ->and($stored)->not->toContain('<iframe');
})->with([
    'script' => ['<script>alert(1)</script>'],
    'noembed' => ['<noembed><img src=x onerror=alert(1)></noembed>'],
    'svg style' => ['<svg><style><img src=x onerror=alert(1)></style></svg>'],
    // NOTE: <plaintext> swallows the rest of the document by definition, so the
    // canary cannot follow it — covered separately below.
    'iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
]);

it('drops a plaintext payload entirely rather than rendering it', function () {
    $page = cmsPage();
    cmsSaveAndFetch($page, '<plaintext><img src=x onerror=alert(1)>');

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('');
});

// ── Content must not be silently LOST ────────────────────────────────────

/**
 * A body whose first tag is a stray CLOSING tag used to close the sanitizer's
 * own wrapper, so everything after it fell outside the parsed root and was
 * dropped — storing '' over a live page while the admin was told "Page
 * updated". `required` could not catch it: validation runs on the RAW input,
 * before sanitising. No wrapper tag is immune (switching to <body> just moves
 * the trigger to </body>), so clean() now falls back to escaped plain text.
 */
it('keeps the author\'s words when the markup is too broken to parse', function (string $label, string $body, string $expected) {
    $page = cmsPage();
    $html = cmsSaveAndFetch($page, $body);

    expect($page->fresh()->getTranslation('body', 'en'))->not->toBe('')
        ->and($html)->toContain($expected);
})->with([
    ['leading stray close', '</div><p>my new page</p></div></div>', 'my new page'],
    ['layout escape', '</div></main><p>KEEP-THESE-WORDS</p>', 'KEEP-THESE-WORDS'],
    ['closing then heading', '</section><h2>Title</h2><p>text</p>', 'Title'],
]);

it('still drops script bodies when it falls back to plain text', function () {
    $page = cmsPage();
    cmsSaveAndFetch($page, '</div><script>alert(1)</script><p>visible</p>');

    $stored = $page->fresh()->getTranslation('body', 'en');

    expect($stored)->toContain('visible')
        ->and($stored)->not->toContain('alert(1)');
});

it('leaves a genuinely empty body empty', function () {
    expect(\App\Support\HtmlSanitizer::clean('   ', \App\Support\HtmlSanitizer::CMS_TAGS))->toBe('');
});

// ── Bounds ───────────────────────────────────────────────────────────────

it('rejects a body past the column limit instead of truncating it', function () {
    $page = cmsPage();

    Livewire::actingAs(cmsAdmin())
        ->test(Pages::class)
        ->call('edit', $page->id)
        ->set('body.en', str_repeat('a', 65001))
        ->call('save')
        ->assertHasErrors(['body.en']);

    expect($page->fresh()->getTranslation('body', 'en'))->toBe('<p>original body</p>');
});

// ── Help articles: the other public CMS surface ──────────────────────────

function cmsArticle(bool $active = true): App\Models\HelpArticle
{
    return App\Models\HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'QA Article'],
        'body' => ['en' => '<p>original article</p>'],
        'position' => 0,
        'is_active' => $active,
    ]);
}

it('publishes an edited help article to the public page', function () {
    $article = cmsArticle();

    Livewire::actingAs(cmsAdmin())
        ->test(App\Livewire\Admin\Support\Articles::class)
        ->call('edit', $article->id)
        ->set('body.en', '<h2>New</h2><p>ARTICLE-MARKER-7</p>')
        ->call('save')
        ->assertHasNoErrors();

    $html = test()->get('/help/article/'.$article->id)->getContent();

    expect($html)->toContain('ARTICLE-MARKER-7')
        ->and($html)->toContain('<h2>New</h2>')
        ->and($html)->not->toContain('original article');
});

it('takes an unpublished help article off the storefront', function () {
    $article = cmsArticle();
    test()->get('/help/article/'.$article->id)->assertOk();

    Livewire::actingAs(cmsAdmin())
        ->test(App\Livewire\Admin\Support\Articles::class)
        ->call('toggleActive', $article->id);

    test()->get('/help/article/'.$article->id)->assertNotFound();
});

it('does not lose a help article body to broken markup either', function () {
    $article = cmsArticle();

    Livewire::actingAs(cmsAdmin())
        ->test(App\Livewire\Admin\Support\Articles::class)
        ->call('edit', $article->id)
        ->set('body.en', '</div><p>ARTICLE-KEEP-ME</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($article->fresh()->getTranslation('body', 'en'))->toContain('ARTICLE-KEEP-ME');
    expect(test()->get('/help/article/'.$article->id)->getContent())->toContain('ARTICLE-KEEP-ME');
});
