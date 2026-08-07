<?php

/**
 * QA — "Relevance" must mean relevance.
 *
 * Reported 2026-08-07: searching "honey" returned 11 products sorted by
 * Relevance with Hazelnut Spread with Cocoa first and Manuka Honey UMF10 fifth.
 * The sort was `orderByDesc('sold_count')` over everything that matched, so a
 * description mention on a better-selling product beat a name match.
 */

use App\Enums\ProductStatus;
use App\Models\Product;

function srProduct(string $name, string $description, int $sold): Product
{
    return Product::factory()->create([
        'name' => ['en' => $name, 'ms' => $name],
        'description' => ['en' => $description, 'ms' => $description],
        'status' => ProductStatus::Live,
        'sold_count' => $sold,
    ]);
}

it('ranks a name match above a better-selling description match', function () {
    $spread = srProduct('Hazelnut Spread with Cocoa', 'Delicious with honey on toast.', 5000);
    $honey = srProduct('Manuka Honey UMF10', 'Raw monofloral from New Zealand.', 12);

    $ids = Product::searchKeywordIds('honey');

    expect($ids)->toContain($honey->id)
        ->and($ids)->toContain($spread->id)
        ->and(array_search($honey->id, $ids, true))
        ->toBeLessThan(array_search($spread->id, $ids, true));
});

it('still orders by popularity within the name-match tier', function () {
    $quiet = srProduct('Acacia Honey Jar', 'Light and floral.', 3);
    $popular = srProduct('Wild Honey Jar', 'Dark and rich.', 900);

    $ids = Product::searchKeywordIds('honey');

    expect(array_search($popular->id, $ids, true))
        ->toBeLessThan(array_search($quiet->id, $ids, true));
});

it('still finds description-only matches', function () {
    $only = srProduct('Breakfast Bundle', 'Includes a jar of honey.', 1);

    expect(Product::searchKeywordIds('honey'))->toContain($only->id);
});
