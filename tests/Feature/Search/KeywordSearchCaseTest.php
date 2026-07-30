<?php

use App\Enums\ProductStatus;
use App\Models\Product;

/**
 * Product name/description are JSON columns. MySQL collates JSON as BINARY, so a
 * plain `LIKE '%beras%'` never matches "Beras Wangi…" — while SQLite's TEXT LIKE
 * is case-insensitive and hides it. That is why the preview returned 8 results
 * for "Beras" and 0 for "beras" after passing locally.
 *
 * These assert the CONTRACT (a search is case-insensitive), so they hold on
 * whichever driver the suite runs against.
 */
it('matches a product name regardless of the case typed', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Beras Wangi Super Tempatan', 'ms' => 'Beras Wangi Super Tempatan'],
        'status' => ProductStatus::Live,
    ]);

    // toContain() takes further EXPECTED VALUES, not a message — passing one
    // asserts the array contains the message string too, and fails misleadingly.
    foreach (['Beras', 'beras', 'BERAS', 'bErAs'] as $term) {
        expect(Product::searchKeywordIds($term))->toContain($product->id);
    }
});

it('matches a description regardless of case', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Unrelated Name', 'ms' => 'Nama Lain'],
        'description' => ['en' => 'Certified by JAKIM under MS 1500:2019', 'ms' => 'Disahkan JAKIM'],
        'status' => ProductStatus::Live,
    ]);

    expect(Product::searchKeywordIds('jakim'))->toContain($product->id);
});

/**
 * ⚠ The two tests above CANNOT FAIL on SQLite: its LIKE is already
 * case-insensitive for ASCII, so they pass with or without the fix. Reverting
 * the model to a plain `LIKE` was verified to leave them green — a guard that
 * cannot fail is not a guard.
 *
 * This one has teeth on every driver: it asserts the query LOWERCASES both
 * sides. That is implementation rather than behaviour, and normally the wrong
 * thing to pin — but the behaviour is unobservable on the suite's own driver,
 * and the bug it prevents (silent, prod-only, whole-feature) is worth it.
 */
it('lowercases both sides of the comparison, which SQLite cannot prove', function () {
    $sql = Product::query()->keywordSearch('Beras')->toSql();

    // Count, do not just look for the string: toSql() also contains the store
    // and brand subqueries, which have their own LOWER(name). Asserting mere
    // presence passed even with the product-name clause reverted — verified.
    // Four lowercased comparisons: name, description, store.name, brand.name.
    expect(substr_count($sql, 'LOWER('))->toBe(4)
        ->and($sql)->toContain('LOWER(description)');

    $bindings = Product::query()->keywordSearch('BeRaS')->getBindings();
    expect($bindings)->toContain('%beras%');
});
