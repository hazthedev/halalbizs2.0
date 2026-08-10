<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;

it('renders a seeded static page with its title', function () {
    $this->seed(PageSeeder::class);

    $this->get('/page/about')
        ->assertOk()
        ->assertSee('About Us');
});

it('returns 404 for an unknown page', function () {
    $this->get('/page/unknown')->assertNotFound();
});

it('returns 404 for an inactive page', function () {
    Page::create([
        'slug' => 'draft',
        'title' => ['en' => 'Draft Page'],
        'body' => ['en' => '<p>Not yet published.</p>'],
        'is_active' => false,
    ]);

    $this->get('/page/draft')->assertNotFound();
});

/**
 * PageSeeder is create-only, and runs on every deploy.
 *
 * It used to updateOrCreate, which reverted admin CMS edits on every run — so it
 * was kept out of deploy.sh, which in turn meant a NEW page added in a PR simply
 * never appeared in production (affiliate-terms, 2026-08-10: 404 on the preview
 * with the whole suite green locally). Both halves are asserted here because
 * fixing one without the other reintroduces the opposite bug.
 */
it('publishes new CMS pages but never overwrites an edited one', function () {
    $this->seed(Database\Seeders\PageSeeder::class);

    $page = App\Models\Page::where('slug', 'affiliate-terms')->firstOrFail();

    // An admin rewrites it in the panel.
    $page->forceFill(['body' => ['en' => '<p>Edited by a human.</p>', 'ms' => '<p>Disunting.</p>']])->save();

    // Next deploy runs the seeder again.
    $this->seed(Database\Seeders\PageSeeder::class);

    expect(App\Models\Page::where('slug', 'affiliate-terms')->first()->getTranslation('body', 'en'))
        ->toBe('<p>Edited by a human.</p>');
});
