<?php

/**
 * The marketing landing (/welcome) stays discoverable from the storefront
 * without hijacking '/': the "Discover HalalBizs" footer link. The category
 * strip (Discover / Live / departments) was removed (Haze 2026-11-11), so the
 * footer link is the entry. '/' itself is unchanged for everyone.
 */

use Database\Seeders\RoleSeeder;

beforeEach(fn () => test()->seed(RoleSeeder::class));

test('the storefront home still renders for a guest and links to the landing', function () {
    $response = test()->get('/')->assertOk();

    // '/' is untouched, and the landing is reachable from it.
    $response->assertSee(route('landing'), false)
        ->assertSee('Discover HalalBizs');
});

test('the category strip under the header is gone (Haze 2026-11-11)', function () {
    test()->get('/')->assertOk()
        ->assertDontSee('aria-label="'.__('Categories').'"', false);
});

test('the landing itself is reachable directly', function () {
    test()->get(route('landing'))->assertOk();
});
