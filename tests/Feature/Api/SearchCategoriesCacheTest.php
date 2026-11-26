<?php

use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** SQL text of every query the callback ran. @return list<string> */
function queriesOf(Closure $run): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    DB::disableQueryLog();

    return array_column(DB::getQueryLog(), 'query');
}

test('a repeated search skips the keyword LIKE scan and gives the same answer', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Cache kurma']]);

    $first = queriesOf(fn () => $this->getJson('/api/v1/search?q=kurma')->assertOk()->assertJsonPath('data.0.id', $product->id));
    $second = queriesOf(fn () => $this->getJson('/api/v1/search?q=%20KURMA%20')->assertOk()->assertJsonPath('data.0.id', $product->id));

    $scans = fn (array $sql) => count(array_filter($sql, fn ($q) => stripos($q, ' like ') !== false));
    expect($scans($first))->toBeGreaterThan(0)->and($scans($second))->toBe(0)->and(count($second))->toBeLessThan(count($first));
});

test('filters, sort and page are part of the answer, not shared through the cache', function () {
    $a = Product::factory()->create(['name' => ['en' => 'Aut cheap'], 'sold_count' => 1]);
    $b = Product::factory()->create(['name' => ['en' => 'Aut dear'], 'sold_count' => 9]);
    $a->variants->first()->update(['price_sen' => 100]);
    $b->variants->first()->update(['price_sen' => 900]);

    $this->getJson('/api/v1/search?q=aut&sort=price_asc')->assertJsonPath('data.0.id', $a->id);
    $this->getJson('/api/v1/search?q=aut&sort=price_desc')->assertJsonPath('data.0.id', $b->id);
    $this->getJson('/api/v1/search?q=aut&sort=price_asc')->assertJsonPath('data.0.id', $a->id);
});

test('a product that goes hidden leaves a cached search at once', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Hide halwa']]);
    $this->getJson('/api/v1/search?q=halwa')->assertJsonCount(1, 'data');

    $product->store->forceFill(['status' => StoreStatus::Suspended])->save();

    $this->getJson('/api/v1/search?q=halwa')->assertJsonCount(0, 'data');
});

test('a new product shows up in a cached search at once', function () {
    $this->getJson('/api/v1/search?q=zaatar')->assertJsonCount(0, 'data');

    Product::factory()->create(['name' => ['en' => 'Zaatar mix']]);

    $this->getJson('/api/v1/search?q=zaatar')->assertJsonCount(1, 'data');
});

test('search answers carry no per-user data, so a signed-in buyer cannot leak into a guest', function () {
    Product::factory()->create(['name' => ['en' => 'Shared dates']]);
    $buyer = User::factory()->create();

    $asBuyer = $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/search?q=dates')->assertOk()->json();
    $this->app['auth']->forgetGuards();
    $asGuest = $this->getJson('/api/v1/search?q=dates')->assertOk()->json();

    expect($asGuest)->toBe($asBuyer);
    expect(json_encode($asGuest))->not->toContain((string) $buyer->email)->not->toContain('wishlist');
});

test('categories are served from cache on repeat and refresh when a category changes', function () {
    $category = Category::factory()->create(['is_active' => true, 'name' => ['en' => 'Old name']]);

    $first = queriesOf(fn () => $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.name', 'Old name'));
    $second = queriesOf(fn () => $this->getJson('/api/v1/categories')->assertOk());
    expect($first)->not->toBeEmpty()->and($second)->toBe([]);

    $category->update(['name' => ['en' => 'New name']]);
    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'New name');

    $category->delete();
    $this->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
});

test('categories cache is per locale', function () {
    Category::factory()->create(['is_active' => true, 'name' => ['en' => 'Dates', 'ms' => 'Kurma']]);

    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'Dates');
    $this->withHeader('Accept-Language', 'ms')->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'Kurma');
});
