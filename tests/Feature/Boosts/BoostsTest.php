<?php

use App\Enums\BoostStatus;
use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Livewire\Admin\Finance\Boosts as AdminBoosts;
use App\Livewire\Seller\Boosts as SellerBoosts;
use App\Livewire\Storefront\Home;
use App\Livewire\Storefront\Listing;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function boostsSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/** Seed available balance the only honest way — a positive ledger entry. */
function boostsFund(Store $store, int $amountSen): void
{
    $store->ledgerEntries()->create([
        'type' => LedgerEntryType::Sale,
        'amount_sen' => $amountSen,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Test sale',
        'created_at' => now(),
    ]);
}

function boostsLiveProduct(Store $store, array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'store_id' => $store->id,
        'sold_count' => 0,
    ], $attributes));
}

function boostsMake(Product $product, array $attributes = []): ProductBoost
{
    return ProductBoost::create(array_merge([
        'product_id' => $product->id,
        'store_id' => $product->store_id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDays(5),
        'amount_sen' => 1000,
        'status' => BoostStatus::Active,
    ], $attributes));
}

// ── Charging ────────────────────────────────────────────────────────────

test('boosting debits the ledger exactly days × price per day and opens the window', function () {
    $this->freezeTime();

    $seller = boostsSeller();
    $store = $seller->store;
    $product = boostsLiveProduct($store);

    boostsFund($store, 10000);

    Livewire::actingAs($seller)
        ->test(SellerBoosts::class)
        ->set('productId', $product->id)
        ->set('days', 5)
        ->call('boost')
        ->assertHasNoErrors();

    $boost = ProductBoost::sole();

    expect($boost->product_id)->toBe($product->id)
        ->and($boost->store_id)->toBe($store->id)
        ->and($boost->status)->toBe(BoostStatus::Active)
        ->and($boost->amount_sen)->toBe(1000) // 5 days × RM2/day (200 sen)
        ->and($boost->starts_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($boost->ends_at->toDateTimeString())->toBe(now()->addDays(5)->toDateTimeString());

    $entry = $store->ledgerEntries()->where('type', LedgerEntryType::Boost)->sole();

    expect($entry->amount_sen)->toBe(-1000)
        ->and($store->availableBalanceSen())->toBe(9000);
});

test('a boost is refused when the available balance cannot cover it', function () {
    $seller = boostsSeller();
    $store = $seller->store;
    $product = boostsLiveProduct($store);

    boostsFund($store, 500); // 7 days costs 1400

    Livewire::actingAs($seller)
        ->test(SellerBoosts::class)
        ->set('productId', $product->id)
        ->set('days', 7)
        ->call('boost')
        ->assertDispatched('toast');

    expect(ProductBoost::count())->toBe(0)
        ->and($store->ledgerEntries()->where('type', LedgerEntryType::Boost)->count())->toBe(0)
        ->and($store->availableBalanceSen())->toBe(500);
});

test('the per-store active boost limit is enforced and nothing is charged past it', function () {
    $seller = boostsSeller();
    $store = $seller->store;

    boostsFund($store, 100000);

    foreach (range(1, 3) as $i) {
        boostsMake(boostsLiveProduct($store));
    }

    $fourth = boostsLiveProduct($store);

    Livewire::actingAs($seller)
        ->test(SellerBoosts::class)
        ->set('productId', $fourth->id)
        ->set('days', 3)
        ->call('boost')
        ->assertDispatched('toast');

    expect(ProductBoost::count())->toBe(3)
        ->and($store->ledgerEntries()->where('type', LedgerEntryType::Boost)->count())->toBe(0);
});

test('days outside 1–30 are rejected', function () {
    $seller = boostsSeller();
    $product = boostsLiveProduct($seller->store);

    boostsFund($seller->store, 100000);

    Livewire::actingAs($seller)
        ->test(SellerBoosts::class)
        ->set('productId', $product->id)
        ->set('days', 31)
        ->call('boost')
        ->assertHasErrors(['days' => 'between']);

    expect(ProductBoost::count())->toBe(0);
});

// ── Leakage ─────────────────────────────────────────────────────────────

test("a seller cannot boost another store's product", function () {
    $sellerA = boostsSeller();
    $foreignProduct = boostsLiveProduct($sellerA->store);

    $sellerB = boostsSeller();
    boostsFund($sellerB->store, 100000);

    Livewire::actingAs($sellerB)
        ->test(SellerBoosts::class)
        ->set('productId', $foreignProduct->id)
        ->set('days', 3)
        ->call('boost')
        ->assertHasErrors(['productId']);

    expect(ProductBoost::count())->toBe(0);
});

test('the preselected ?product= id is dropped unless it is an own live product', function () {
    $sellerA = boostsSeller();
    $foreignProduct = boostsLiveProduct($sellerA->store);

    $sellerB = boostsSeller();

    Livewire::actingAs($sellerB)
        ->withQueryParams(['product' => $foreignProduct->id])
        ->test(SellerBoosts::class)
        ->assertSet('productId', null);
});

// ── Cancel + expiry ─────────────────────────────────────────────────────

test('cancelling a boost flips it to cancelled without refunding the fee', function () {
    $seller = boostsSeller();
    $store = $seller->store;
    $product = boostsLiveProduct($store);

    boostsFund($store, 10000);

    Livewire::actingAs($seller)
        ->test(SellerBoosts::class)
        ->set('productId', $product->id)
        ->set('days', 5)
        ->call('boost')
        ->call('cancel', ProductBoost::sole()->id)
        ->assertDispatched('toast');

    expect(ProductBoost::sole()->status)->toBe(BoostStatus::Cancelled)
        ->and($store->availableBalanceSen())->toBe(9000); // no refund (v1)
});

test("a seller cannot cancel another store's boost", function () {
    $sellerA = boostsSeller();
    $boost = boostsMake(boostsLiveProduct($sellerA->store));

    Livewire::actingAs(boostsSeller())
        ->test(SellerBoosts::class)
        ->call('cancel', $boost->id)
        ->assertNotFound();

    expect($boost->fresh()->status)->toBe(BoostStatus::Active);
});

test('boosts:expire flips only active boosts whose window has ended', function () {
    $seller = boostsSeller();

    $ended = boostsMake(boostsLiveProduct($seller->store), [
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subHour(),
    ]);
    $running = boostsMake(boostsLiveProduct($seller->store));
    $cancelled = boostsMake(boostsLiveProduct($seller->store), [
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subHour(),
        'status' => BoostStatus::Cancelled,
    ]);

    $this->artisan('boosts:expire')->assertSuccessful();

    expect($ended->fresh()->status)->toBe(BoostStatus::Expired)
        ->and($running->fresh()->status)->toBe(BoostStatus::Active)
        ->and($cancelled->fresh()->status)->toBe(BoostStatus::Cancelled);
});

// ── Sponsored placement ─────────────────────────────────────────────────

test('actively boosted products lead page 1 of the category listing with a Sponsored label, deduped', function () {
    $seller = boostsSeller();
    $category = Category::factory()->create();

    $boosted = boostsLiveProduct($seller->store, [
        'name' => ['en' => 'Boosted Gula Melaka'],
        'category_id' => $category->id,
        'published_at' => now()->subDays(30), // organic latest-sort would bury it
    ]);
    $organic = boostsLiveProduct($seller->store, [
        'name' => ['en' => 'Fresh Pandan Cake'],
        'category_id' => $category->id,
        'published_at' => now(),
    ]);

    boostsMake($boosted);

    $component = Livewire::test(Listing::class, ['category' => $category])
        ->assertSee('Sponsored')
        ->assertSeeInOrder(['Boosted Gula Melaka', 'Fresh Pandan Cake']);

    // Deduped: the boosted card renders exactly once.
    expect(substr_count($component->html(), 'wire:key="product-'.$boosted->id.'"'))->toBe(1);
});

test('cancelling a boost removes the sponsored placement from the listing', function () {
    $seller = boostsSeller();
    $category = Category::factory()->create();

    $product = boostsLiveProduct($seller->store, ['category_id' => $category->id]);
    $boost = boostsMake($product);

    Livewire::test(Listing::class, ['category' => $category])->assertSee('Sponsored');

    $boost->update(['status' => BoostStatus::Cancelled]);

    Livewire::test(Listing::class, ['category' => $category])->assertDontSee('Sponsored');
});

test('sponsored products respect the search scope and only matching boosts are injected', function () {
    // phpunit.xml pins SCOUT_DRIVER=null — search needs the collection driver.
    config(['scout.driver' => 'collection']);

    $seller = boostsSeller();

    $matching = boostsLiveProduct($seller->store, ['name' => ['en' => 'Sambal Ikan Bilis Pedas']]);
    $unrelated = boostsLiveProduct($seller->store, ['name' => ['en' => 'Plain Cotton Tudung']]);

    boostsMake($matching);
    boostsMake($unrelated);

    Livewire::withQueryParams(['q' => 'Sambal'])
        ->test(Listing::class)
        ->assertSee('Sponsored')
        ->assertSee('Sambal Ikan Bilis Pedas')
        ->assertDontSee('Plain Cotton Tudung');
});

test("the home 'top' source places boosted products first, flagged, then fills organically", function () {
    $seller = boostsSeller();

    $boosted = boostsLiveProduct($seller->store, [
        'name' => ['en' => 'Boosted Honey Jar'],
        'sold_count' => 0,
    ]);
    $popular = boostsLiveProduct($seller->store, [
        'name' => ['en' => 'Bestselling Dates Box'],
        'sold_count' => 5000,
    ]);

    boostsMake($boosted);

    HomeSection::create([
        'type' => 'product_grid',
        'title' => ['en' => 'Popular now'],
        'payload' => ['source' => 'top', 'limit' => 6],
        'position' => 0,
        'is_active' => true,
    ]);

    $component = Livewire::test(Home::class)
        ->assertSee('Sponsored')
        ->assertSeeInOrder(['Boosted Honey Jar', 'Bestselling Dates Box']);

    // Deduped in the grid as well.
    expect(substr_count($component->html(), 'wire:key="grid-'.HomeSection::sole()->id.'-'.$boosted->id.'"'))->toBe(1);
});

// ── Admin oversight ─────────────────────────────────────────────────────

test('admin finance boosts lists every boost and sums revenue, cancelled included', function () {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $seller = boostsSeller();
    boostsMake(boostsLiveProduct($seller->store), ['amount_sen' => 1000]);
    boostsMake(boostsLiveProduct($seller->store), ['amount_sen' => 2000, 'status' => BoostStatus::Cancelled]);

    Livewire::actingAs($admin)
        ->test(AdminBoosts::class)
        ->assertSee($seller->store->name)
        ->assertSee('RM 30.00') // 1000 + 2000 sen — cancelled still counts
        ->assertSee('platform income');
});
