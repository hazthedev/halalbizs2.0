<?php

use App\Livewire\Storefront\Home;
use App\Livewire\Storefront\Layout\MiniCart;
use App\Livewire\Storefront\Listing;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\WishlistService;
use App\Settings\GeneralSettings;
use Database\Seeders\HomeSectionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function storefrontSpeedQueries(string $label, Closure $action): array
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $action();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    fwrite(STDERR, "SPEED {$label}: ".count($queries)." queries\n");

    return $queries;
}

it('reuses public home sections without querying the catalogue on a warm visit', function () {
    $this->seed(HomeSectionSeeder::class);
    Product::factory()->create();
    Cache::flush();
    Livewire::test(Home::class);
    $queries = storefrontSpeedQueries('home warm', fn () => Livewire::test(Home::class));
    expect(collect($queries)->filter(fn ($query) => str_contains($query['query'], 'from `products`'))->count())->toBe(0);
});

it('saves a card heart without rendering its parent page', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $page = Livewire::actingAs($user)->test(Listing::class);
    storefrontSpeedQueries('card heart', fn () => $page->call('toggleWishlist', $product->id));
    expect($page->effects)->not->toHaveKey('html');
    expect(app(WishlistService::class)->ids($user))->toBe([$product->id]);
});

it('adds a card to the cart without rendering its parent page', function () {
    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = true;
    $settings->save();
    $product = Product::factory()->create();
    $product->variants()->update(['stock' => 10]);
    $page = Livewire::test(Listing::class);
    storefrontSpeedQueries('card add', fn () => $page->call('addToCart', $product->variants->first()->id));
    expect($page->effects)->not->toHaveKey('html');
    $page->assertReturned(true)->assertDispatched('cart-updated');
});

it('walks a category tree in one query and refreshes after a category save', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $leaf = Category::factory()->create(['parent_id' => $child->id]);
    $queries = storefrontSpeedQueries('category tree', fn () => expect($root->descendantIds())->toBe([$root->id, $child->id, $leaf->id]));
    expect(count($queries))->toBeLessThanOrEqual(1);
    $leaf->update(['parent_id' => $root->id]);
    expect($child->fresh()->descendantIds())->toBe([$child->id]);
});

it('reuses listing lookups and builds one responsive set of filters', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    Category::factory()->create(['parent_id' => $child->id]);
    Product::factory()->create(['category_id' => $child->id, 'cod_enabled' => true]);
    $page = Livewire::test(Listing::class, ['category' => $root]);
    $queries = storefrontSpeedQueries('listing filter', fn () => $page->set('cod', true));
    expect(count($queries))->toBeLessThanOrEqual(10);
    expect(substr_count($page->html(), 'id="listing-price-min"'))->toBe(1);
});

it('returns recently viewed cards without redrawing the home and never shares history', function () {
    $this->seed(HomeSectionSeeder::class);
    $first = Product::factory()->create(['name' => ['en' => 'Private history tea']]);
    $other = Product::factory()->create(['name' => ['en' => 'Other history soap']]);
    $page = Livewire::test(Home::class);
    storefrontSpeedQueries('home recent', fn () => $page->call('loadRecentlyViewed', [$first->id]));
    expect($page->effects)->not->toHaveKey('html');
    expect($page->effects['returns'][0])->toContain('Private history tea')->not->toContain('Other history soap');
    $page = Livewire::test(Home::class)->call('loadRecentlyViewed', [$other->id]);
    expect($page->effects['returns'][0])->toContain('Other history soap')->not->toContain('Private history tea');
});

it('loads the personal mini cart only when opened', function () {
    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = true;
    $settings->save();
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => ['en' => 'My cart tea']]);
    $product->variants()->update(['stock' => 10]);
    app(CartService::class)->addItem($user, $product->variants->first(), 1);
    $page = null;
    $queries = storefrontSpeedQueries('mini cart initial', function () use ($user, &$page) {
        $page = Livewire::actingAs($user)->test(MiniCart::class);
    });
    expect(collect($queries)->filter(fn ($query) => str_contains($query['query'], 'from `cart_items`')))->toBeEmpty();
    $page->call('loadCart')->assertSee('My cart tea');
});

it('reuses public footer links and invalidates them when a page is published', function () {
    $page = Page::create(['slug' => 'public-guide', 'title' => ['en' => 'Public guide'], 'body' => ['en' => 'Guide'], 'is_active' => true, 'show_in_footer' => true]);
    // Exercise the actual full-page layout on both main and the speed branch.
    $this->get('/')->assertOk()->assertSee('Public guide');
    $queries = storefrontSpeedQueries('layout warm', fn () => $this->get('/')->assertOk());
    expect(collect($queries)->filter(fn ($query) => str_contains($query['query'], 'from `pages`')))->toBeEmpty();
    $page->update(['title' => ['en' => 'Updated guide']]);
    $this->get('/')->assertOk()->assertSee('Updated guide')->assertDontSee('Public guide');
});

it('reuses search ids across filter requests without sharing a different term', function () {
    config(['scout.driver' => 'collection']);
    $product = Product::factory()->create(['name' => ['en' => 'Speed peppermint tea'], 'cod_enabled' => true]);
    $page = Livewire::withQueryParams(['q' => 'peppermint'])->test(Listing::class)->assertSee('Speed peppermint tea');
    $queries = storefrontSpeedQueries('search filter', fn () => $page->set('cod', true));
    expect(count($queries))->toBeLessThanOrEqual(7);
    $page->set('q', 'unmatched-speed-term')->assertDontSee('Speed peppermint tea');
});

it('reads warm public caches when PHP object unserialization is disabled', function () {
    config(['cache.stores.array.serialize' => true, 'cache.serializable_classes' => false]);
    Cache::purge('array');
    $this->seed(HomeSectionSeeder::class);
    $product = Product::factory()->create(['name' => ['en' => 'Serialization safe tea']]);
    Livewire::test(Home::class)->assertSee('Serialization safe tea');
    Livewire::test(Home::class)->assertSee('Serialization safe tea');
    $this->get('/')->assertOk();
    $this->get('/')->assertOk();
    Livewire::test(Listing::class, ['category' => $product->category]);
    Livewire::test(Listing::class, ['category' => $product->category]);
});
