<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\Landing;
use App\Livewire\Storefront\Listing;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

// Audit M-20, M-21, M-22/23/24. Query COUNTS, not timings: a timing assertion on
// a shared runner is a flake, but "this page must not issue one query per card"
// is a property that either holds or does not.

function countQueries(Closure $work): int
{
    $n = 0;
    DB::listen(function () use (&$n) {
        $n++;
    });

    $work();

    return $n;
}

// ── M-20 · one extra SELECT per card on the hottest pages ────────────────
test('a product grid does not lazy-load a certificate per card', function () {
    $store = Store::factory()->approved()->create();
    $category = Category::factory()->create();

    $cert = HalalCertificate::create([
        'store_id' => $store->id, 'number' => 'MY-JKM-5150-001', 'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM', 'holder_name' => 'Budget Holder',
        'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);

    Product::factory()->count(12)->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'halal_certificate_id' => $cert->id,
        'status' => ProductStatus::Live,
    ]);

    $queries = countQueries(fn () => Livewire::test(Listing::class)->html());

    // product-card calls halalVerdict(), which touches the BelongsTo. Unloaded,
    // twelve cards were twelve extra SELECTs; the eager load makes it one.
    // MEASURED, not guessed: 20 queries before the eager load, 9 after, with
    // this fixture's 12 products. 12 leaves headroom for an unrelated query
    // without letting the per-card SELECT back in — a ceiling of 40 passed
    // against the bug, which would have made this test worthless.
    expect($queries)->toBeLessThan(12);
});

// ── M-21 · four queries per department inside a @foreach ─────────────────
test('the landing department tiles do not query per category', function () {
    $store = Store::factory()->approved()->create();

    foreach (range(1, 6) as $i) {
        $parent = Category::factory()->create(['parent_id' => null, 'position' => $i]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        Product::factory()->count(2)->create([
            'store_id' => $store->id, 'category_id' => $child->id, 'status' => ProductStatus::Live,
        ]);
    }

    $queries = countQueries(fn () => Livewire::test(Landing::class)->html());

    // Six departments cost four queries EACH inside the @foreach, six of those
    // ORDER BY RAND(), on a page that is deliberately uncached.
    // MEASURED: 37 before, 16 after, with six departments.
    expect($queries)->toBeLessThan(22);
});

// ⚠ THREE LEVELS, and that depth is the whole point of this test.
//
// My first version of it had parent → child → products, which is what the old
// blade counted (direct children only) — so it passed while the LIVE page said
// "No listings yet" on all five departments, because the real catalogue is
// parent → child → grandchild. The tile disagreed with the department page it
// links to, which counts with Category::descendantIds().
test('a department counts products anywhere beneath it, like the page it links to', function () {
    $store = Store::factory()->approved()->create();

    $department = Category::factory()->create(['parent_id' => null, 'position' => 1]);
    $child = Category::factory()->create(['parent_id' => $department->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);

    Product::factory()->count(2)->create([
        'store_id' => $store->id, 'category_id' => $child->id, 'status' => ProductStatus::Live,
    ]);
    Product::factory()->count(3)->create([
        'store_id' => $store->id, 'category_id' => $grandchild->id, 'status' => ProductStatus::Live,
    ]);

    $tile = Livewire::test(Landing::class)->viewData('categories')->firstWhere('id', $department->id);

    // 5, not 2: the same total Listing would show for this department.
    expect($tile->tile_count)->toBe(5)
        ->and($tile->tile_count)->toBe(Product::live()->whereIn('category_id', $department->descendantIds())->count());
});

// ── M-22/23/24 · the three missing indexes ───────────────────────────────
test('the hot queries have an index to use', function () {
    foreach ([
        'sub_orders' => 'sub_orders_store_status_created_idx',
        'activity_log' => 'activity_log_created_at_idx',
        'product_variants' => 'product_variants_product_price_idx',
    ] as $table => $index) {
        expect(Schema::hasIndex($table, $index))->toBeTrue("missing {$index} on {$table}");
    }
});
