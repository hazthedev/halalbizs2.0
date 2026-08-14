<?php

use App\Enums\HelpCategory;
use App\Livewire\Seller\Products\Index as SellerProducts;
use App\Livewire\Storefront\Help\Index as HelpIndex;
use App\Livewire\Storefront\Layout\SearchOverlay;
use App\Models\Category;
use App\Models\HelpArticle;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\JsonSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Audit M-19. MySQL gives a JSON column a BINARY collation, so `LIKE '%beras%'`
// never matches "Beras Wangi". Product::keywordSearch fixed and documented this;
// three other readers never got the recipe and have been silently broken in
// production ever since.
//
// ⚠ THESE TESTS ARE MEANINGLESS ON SQLITE, which stores the same column as TEXT
// with a case-insensitive LIKE — the exact reason the bug survived a green
// suite. The guard below states that out loud rather than passing vacuously.
beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
});

test('the seller can find their own product by a lowercase name', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);

    $product = Product::factory()->create([
        'store_id' => $store->id,
        'name' => ['en' => 'Beras Wangi Premium', 'ms' => 'Beras Wangi Premium'],
    ]);

    $ids = Livewire::actingAs($seller->fresh())->test(SellerProducts::class)
        ->set('search', 'beras')
        ->viewData('products')->pluck('id');

    expect($ids)->toContain($product->id);
});

test('the header overlay still suggests a category typed in lowercase', function () {
    $category = Category::factory()->create(['name' => ['en' => 'Rice & Grains', 'ms' => 'Beras']]);

    $names = Livewire::test(SearchOverlay::class)
        ->set('query', 'rice')
        ->viewData('categories')->pluck('id');

    expect($names)->toContain($category->id);
});

test('help search matches an article typed in lowercase', function () {
    // No HelpArticleFactory exists — build it the way the neighbours do.
    $article = HelpArticle::create([
        'title' => ['en' => 'How do I track my order?', 'ms' => 'Bagaimana menjejak pesanan?'],
        'body' => ['en' => 'Open your account and pick the order.', 'ms' => 'Buka akaun anda.'],
        'category' => HelpCategory::cases()[0],
        'position' => 1,
        'is_active' => true,
    ]);

    // The component groups by category before handing the view `groups`.
    $ids = collect(Livewire::test(HelpIndex::class)
        ->set('search', 'how do i track')
        ->viewData('groups'))
        ->flatMap(fn (array $group) => $group['articles']->pluck('id'));

    expect($ids)->toContain($article->id);
});

// The escaping half. A bare % or _ is a LIKE wildcard, so unescaped it returns
// the whole table — which reads as "search is broken" in the other direction.
test('a wildcard typed by a user is a literal, not a match-everything', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);

    Product::factory()->create(['store_id' => $store->id, 'name' => ['en' => 'Kurma Ajwa', 'ms' => 'Kurma Ajwa']]);

    $ids = Livewire::actingAs($seller->fresh())->test(SellerProducts::class)
        ->set('search', '%')
        ->viewData('products')->pluck('id');

    expect($ids)->toBeEmpty();
});

test('the pattern helper lowercases and neutralises LIKE metacharacters', function () {
    expect(JsonSearch::pattern('  BeRaS  '))->toBe('%beras%')
        ->and(JsonSearch::pattern('50%'))->toBe('%50!%%')
        ->and(JsonSearch::pattern('a_b'))->toBe('%a!_b%')
        ->and(JsonSearch::pattern('!'))->toBe('%!!%')
        ->and(JsonSearch::pattern(null))->toBe('%%');
});

test('a locale path becomes a JSON extraction, not a column name', function () {
    expect(JsonSearch::clause('name'))->toBe("LOWER(name) LIKE ? ESCAPE '!'")
        ->and(JsonSearch::clause('title->en'))
        ->toBe("LOWER(json_unquote(json_extract(`title`, '$.\"en\"'))) LIKE ? ESCAPE '!'");
});
