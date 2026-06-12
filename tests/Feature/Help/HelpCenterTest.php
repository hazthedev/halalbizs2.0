<?php

use App\Livewire\Admin\Support\Articles;
use App\Livewire\Storefront\Help\Article as HelpArticlePage;
use App\Livewire\Storefront\Help\Index as HelpIndex;
use App\Models\HelpArticle;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function helpCenterAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

// ── Public index ────────────────────────────────────────────────────────

test('help index renders seeded articles grouped by category', function () {
    $this->seed(HelpArticleSeeder::class);

    $this->get('/help')
        ->assertOk()
        ->assertSee('Help centre')
        ->assertSee('Buying')
        ->assertSee('Payments')
        ->assertSee('How do I place an order?')
        ->assertSee('Cash on delivery (COD)')
        ->assertSee('Contact support');
});

test('search narrows the article list', function () {
    $this->seed(HelpArticleSeeder::class);

    Livewire::test(HelpIndex::class)
        ->assertSee('How do I place an order?')
        ->assertSee('Tracking your shipment')
        ->set('search', 'voucher')
        ->assertSee('Using vouchers at checkout')
        ->assertDontSee('Tracking your shipment');
});

test('search also matches the article body', function () {
    $this->seed(HelpArticleSeeder::class);

    Livewire::test(HelpIndex::class)
        ->set('search', 'two-factor')
        ->assertSee('Keeping your account secure')
        ->assertDontSee('How do I place an order?');
});

test('inactive articles never appear on the index', function () {
    HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'Hidden draft article'],
        'body' => ['en' => '<p>Draft.</p>'],
        'is_active' => false,
    ]);

    $this->get('/help')->assertOk()->assertDontSee('Hidden draft article');
});

// ── Public article page ─────────────────────────────────────────────────

test('article renders its translated title and body per locale', function () {
    $article = HelpArticle::create([
        'category' => 'shipping',
        'title' => ['en' => 'Shipping zones explained', 'ms' => 'Zon penghantaran diterangkan'],
        'body' => ['en' => '<p>West and East Malaysia rates differ.</p>', 'ms' => '<p>Kadar Semenanjung dan Malaysia Timur berbeza.</p>'],
        'is_active' => true,
    ]);

    $this->get(route('help.article', $article))
        ->assertOk()
        ->assertSee('Shipping zones explained')
        ->assertSee('West and East Malaysia rates differ.');

    $this->withSession(['locale' => 'ms'])
        ->get(route('help.article', $article))
        ->assertOk()
        ->assertSee('Zon penghantaran diterangkan')
        ->assertSee('Kadar Semenanjung dan Malaysia Timur berbeza.');
});

test('article falls back to english when no ms translation exists', function () {
    $article = HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'English-only article'],
        'body' => ['en' => '<p>English body only.</p>'],
        'is_active' => true,
    ]);

    $this->withSession(['locale' => 'ms'])
        ->get(route('help.article', $article))
        ->assertOk()
        ->assertSee('English-only article');
});

test('an inactive article 404s', function () {
    $article = HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'Unpublished'],
        'body' => ['en' => '<p>Hidden.</p>'],
        'is_active' => false,
    ]);

    $this->get(route('help.article', $article))->assertNotFound();
});

test('view count increments once per session', function () {
    $article = HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'Counted article'],
        'body' => ['en' => '<p>Body.</p>'],
        'is_active' => true,
    ]);

    Livewire::test(HelpArticlePage::class, ['article' => $article]);
    expect($article->refresh()->views)->toBe(1);

    // Same session — no double count.
    Livewire::test(HelpArticlePage::class, ['article' => $article]);
    expect($article->refresh()->views)->toBe(1);

    // A fresh session counts again.
    session()->forget("help_viewed.{$article->id}");
    Livewire::test(HelpArticlePage::class, ['article' => $article]);
    expect($article->refresh()->views)->toBe(2);
});

test('article page lists related articles from the same category', function () {
    $this->seed(HelpArticleSeeder::class);
    $cod = HelpArticle::where('category', 'payments')->where('position', 0)->sole();

    $this->get(route('help.article', $cod))
        ->assertOk()
        ->assertSee('Related articles')
        ->assertSee('Using vouchers at checkout');
});

// ── Admin CRUD ──────────────────────────────────────────────────────────

test('admin creates a help article with translations and a sanitized body', function () {
    Livewire::actingAs(helpCenterAdmin())
        ->test(Articles::class)
        ->call('create')
        ->set('category', 'payments')
        ->set('title.en', 'COD limits')
        ->set('title.ms', 'Had COD')
        ->set('body.en', '<script>alert(1)</script><p>Up to <strong>RM500</strong>.</p><iframe src="x"></iframe>')
        ->set('body.ms', '<p>Sehingga RM500.</p>')
        ->set('position', '4')
        ->call('save')
        ->assertHasNoErrors();

    $article = HelpArticle::sole();

    expect($article->category->value)->toBe('payments')
        ->and($article->getTranslation('title', 'en'))->toBe('COD limits')
        ->and($article->getTranslation('title', 'ms'))->toBe('Had COD')
        ->and($article->getTranslation('body', 'en'))->toBe('alert(1)<p>Up to <strong>RM500</strong>.</p>')
        ->and($article->getTranslation('body', 'ms'))->toBe('<p>Sehingga RM500.</p>')
        ->and($article->position)->toBe(4)
        ->and($article->is_active)->toBeTrue();
});

test('admin article validation requires english title, english body, and a known category', function () {
    Livewire::actingAs(helpCenterAdmin())
        ->test(Articles::class)
        ->call('create')
        ->set('category', 'nonsense')
        ->set('title.en', '')
        ->set('body.en', '')
        ->call('save')
        ->assertHasErrors(['category', 'title.en', 'body.en']);

    expect(HelpArticle::count())->toBe(0);
});

test('admin updates, toggles, and deletes an article', function () {
    $admin = helpCenterAdmin();
    $article = HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'Old title'],
        'body' => ['en' => '<p>Old body.</p>'],
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Articles::class)
        ->call('edit', $article->id)
        ->set('title.en', 'New title')
        ->set('category', 'account')
        ->call('save')
        ->assertHasNoErrors();

    $article->refresh();
    expect($article->getTranslation('title', 'en'))->toBe('New title')
        ->and($article->category->value)->toBe('account');

    Livewire::actingAs($admin)->test(Articles::class)->call('toggleActive', $article->id);
    expect($article->refresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)->test(Articles::class)->call('delete', $article->id);
    expect(HelpArticle::find($article->id))->toBeNull();
});

test('clearing the ms translation removes it and en remains the fallback', function () {
    $admin = helpCenterAdmin();
    $article = HelpArticle::create([
        'category' => 'buying',
        'title' => ['en' => 'Both locales', 'ms' => 'Kedua-dua bahasa'],
        'body' => ['en' => '<p>EN.</p>', 'ms' => '<p>MS.</p>'],
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Articles::class)
        ->call('edit', $article->id)
        ->set('title.ms', '')
        ->set('body.ms', '')
        ->call('save')
        ->assertHasNoErrors();

    $article->refresh();
    expect($article->getTranslations('title'))->not->toHaveKey('ms')
        ->and($article->getTranslation('title', 'ms'))->toBe('Both locales'); // fallback
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on the admin help-article screen', function () {
    $this->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('admin.support.articles'))->assertForbidden();
});

test('admins can open the help-article screen', function () {
    $this->actingAs(helpCenterAdmin())->get(route('admin.support.articles'))->assertOk();
});
