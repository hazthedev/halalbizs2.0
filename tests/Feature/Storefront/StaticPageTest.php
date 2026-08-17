<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;

it('renders a seeded static page with its title', function () {
    $this->seed(PageSeeder::class);

    $this->get('/page/about')
        ->assertOk()
        ->assertSee('About Us');
});

it('publishes ample trust and company content in all three storefront languages', function () {
    $this->seed(PageSeeder::class);

    $about = Page::where('slug', 'about')->sole();
    $trust = Page::where('slug', 'trust-safety')->sole();

    foreach (['en', 'ms', 'vi'] as $locale) {
        expect(mb_strlen(strip_tags($about->getTranslation('body', $locale, false))))->toBeGreaterThan(1500)
            ->and(mb_strlen(strip_tags($trust->getTranslation('body', $locale, false))))->toBeGreaterThan(2500);
    }

    $this->withSession(['locale' => 'en'])->get('/page/trust-safety')
        ->assertOk()
        ->assertSee('Product-level halal evidence');
    $this->withSession(['locale' => 'ms'])->get('/page/trust-safety')
        ->assertOk()
        ->assertSee('Bukti halal pada peringkat produk');
    $this->withSession(['locale' => 'vi'])->get('/page/trust-safety')
        ->assertOk()
        ->assertSee('Bằng chứng halal ở cấp sản phẩm');

    $this->withSession(['locale' => 'en'])->get('/page/about')
        ->assertSee('/page/trust-safety', false)
        ->assertSee('Trust &amp; safety', false)
        ->assertSee('English &middot; Bahasa Melayu &middot; Tiếng Việt', false);
    $this->withSession(['locale' => 'ms'])->get('/page/about')
        ->assertSee('Kepercayaan &amp; keselamatan', false);
    $this->withSession(['locale' => 'vi'])->get('/page/about')
        ->assertSee('Tin cậy &amp; an toàn', false);
});

it('upgrades the original short about baseline once and preserves later administrator copy', function () {
    $this->seed(PageSeeder::class);

    $about = Page::where('slug', 'about')->sole();
    $about->setTranslation('body', 'en', '<h2>About HalalBizs</h2><p>HalalBizs is a Malaysian multi-vendor marketplace bringing trusted, halal-friendly sellers and shoppers together — with fair fees, buyer protection, and bilingual support.</p>')->save();

    $this->seed(PageSeeder::class);

    expect($about->refresh()->getTranslation('body', 'en', false))->toContain('<h3>Our purpose</h3>');

    $about->setTranslation('body', 'en', '<p>Edited by the company team.</p>')->save();
    $this->seed(PageSeeder::class);

    expect($about->refresh()->getTranslation('body', 'en', false))->toBe('<p>Edited by the company team.</p>');
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
    $this->seed(PageSeeder::class);

    $page = Page::where('slug', 'affiliate-terms')->firstOrFail();

    // An admin rewrites it in the panel.
    $page->forceFill(['body' => ['en' => '<p>Edited by a human.</p>', 'ms' => '<p>Disunting.</p>']])->save();

    // Next deploy runs the seeder again.
    $this->seed(PageSeeder::class);

    expect(Page::where('slug', 'affiliate-terms')->first()->getTranslation('body', 'en'))
        ->toBe('<p>Edited by a human.</p>');
});
