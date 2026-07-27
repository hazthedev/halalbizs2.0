<?php

/**
 * The marketing landing (/welcome) stays discoverable from the storefront
 * without hijacking '/': a "Discover" entry in the category strip + a
 * "Discover HalalBizs" footer link. '/' itself is unchanged for everyone.
 */

use Database\Seeders\RoleSeeder;

beforeEach(fn () => test()->seed(RoleSeeder::class));

test('the storefront home still renders for a guest and links to the landing', function () {
    $response = test()->get('/')->assertOk();

    // '/' is untouched, and the landing is reachable from it.
    $response->assertSee(route('landing'), false)
        ->assertSee('Discover');
});

test('the landing itself is reachable directly', function () {
    test()->get(route('landing'))->assertOk();
});
