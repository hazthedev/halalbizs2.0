<?php

use App\Livewire\Storefront\Landing;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;

/**
 * /welcome was rebuilt 2026-07-30 against the reference design concept, so the
 * previous specs here (seven "Night Souk" landmarks, the word/eyebrow motion
 * hooks, "no shopping chrome") describe a page that no longer exists. They are
 * replaced rather than patched.
 *
 * Two of the old assertions are deliberately INVERTED, and that is the point:
 *   · the page now uses the storefront layout, so the shopping chrome it used to
 *     forbid is expected — in the reference, that chrome is part of the design
 *   · the stats band no longer animates counts, so the count-up hooks are gone
 *
 * What is worth pinning is not the markup but the promises: real counts, honest
 * labelling of what is not built, and the certificate spine.
 */
it('lets a guest view the landing page with the certificate-first pitch', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertSeeLivewire(Landing::class)
        ->assertSee('Nothing is listed here until the certificate checks out.')
        ->assertSee('Browse verified catalogue')
        ->assertSee('Sell on HalalBizs')
        ->assertSee('Four checks between a seller and your basket.');
});

it('renders the reference section order', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertSeeInOrder([
            'Malaysia · Halal marketplace',   // hero eyebrow
            'Certificate before catalogue',   // 01/02/03 trust rail
            'Shop by department',
            'How verification works',         // the dark band
        ]);
});

it('now carries the storefront chrome, which the previous design deliberately hid', function () {
    // Inverted on purpose: the reference's screen has the trust ticker, search
    // and department row, so the landing inherits them.
    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Search products, stores', false)
        ->assertSee('open-mini-cart', false);
});

it('labels the expiry watch as unbuilt instead of claiming it', function () {
    // The page must not promise a check that does not run. Step 04 carries an
    // "In build" chip; if someone removes that chip without building the watch,
    // this fails.
    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Expiry watch')
        ->assertSee('In build');
});

it('shows real top-level departments when they are seeded', function () {
    Category::factory()->create(['name' => ['en' => 'Snacks & Treats', 'ms' => 'Snek & Manisan']]);

    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Shop by department')
        ->assertSee('Snacks & Treats');
});

it('counts the hero figures from the database rather than hardcoding them', function () {
    Store::factory()->approved()->create();
    Product::factory()->create();

    $this->get('/welcome')
        ->assertOk()
        ->assertSee('Certified listings')
        ->assertSee('Audited sellers')
        ->assertSee('Recognised bodies')
        // The reference's own "2,140 verified SKUs" is its placeholder copy and
        // must never appear here.
        ->assertDontSee('2,140');
});

it('omits the brand-in-focus block when no seller has listings', function () {
    // The section reads live data, so with nothing to show it should not render
    // an empty shell.
    $this->get('/welcome')
        ->assertOk()
        ->assertDontSee('Brand in focus');
});
