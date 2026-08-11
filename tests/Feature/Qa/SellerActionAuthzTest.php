<?php

/**
 * QA pass — per-action authorization on the SELLER and STOREFRONT Livewire
 * update endpoint.
 *
 * The 2026-08-06 sweep proved this for admin components (V1: `can:` is on
 * Livewire's persistent-middleware list and re-runs; C8: the two routes with no
 * `can:` gate stay drivable). It explicitly declared the same question for
 * storefront/seller components OUT OF SCOPE. This closes that.
 *
 * The question is not "does the page 302" — V20 already proved entry. It is:
 * given a leaked or stale seller snapshot, does a MUTATING action re-authorize
 * on the /livewire/update round trip, or does route middleware turn out to have
 * been the only gate?
 *
 * Every assertion is on the real endpoint plus the victim's database row.
 */

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\WithFaker;

uses(WithFaker::class);

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

function saSeller(string $storeName = 'Victim Store'): User
{
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('seller');

    Store::factory()->create([
        'user_id' => $user->id,
        'name' => $storeName,
        'status' => StoreStatus::Approved,
    ]);

    return $user->fresh();
}

function saBuyer(): User
{
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('buyer');

    return $user->fresh();
}

/** Pull one component's snapshot JSON out of a rendered page. */
function saSnapshot(string $html, string $componentName): string
{
    preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $json = html_entity_decode($encoded, ENT_QUOTES);

        if (str_contains($json, '"'.$componentName.'"')) {
            return $json;
        }
    }

    throw new RuntimeException("No wire:snapshot found for [{$componentName}].");
}

/** Replay a Livewire action over the REAL /livewire/update endpoint. */
function saReplay(string $snapshot, string $method, array $params = [])
{
    return test()->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]],
    ]);
}

// ── The core question ────────────────────────────────────────────────────

test('a buyer replaying a leaked seller snapshot cannot delist the seller\'s product', function () {
    $seller = saSeller();
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);

    // Seller renders their own product list — this is the snapshot that leaks.
    $html = test()->actingAs($seller)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    // A buyer replays it against the real endpoint.
    test()->actingAs(saBuyer());
    $response = saReplay($snapshot, 'delist', [$product->id]);

    // A refusal is anything that is not a successful component update — the
    // middleware answers with a 302 to the branded door rather than a 403, so
    // asserting >=400 would report a real guard as a failure.
    expect($response->status())->not->toBe(200)
        ->and($product->fresh()->status)->toBe(ProductStatus::Live);
});

test('a rival seller replaying the snapshot cannot delist it either', function () {
    $victim = saSeller('Victim Store');
    $product = Product::factory()->create([
        'store_id' => $victim->store->id,
        'status' => ProductStatus::Live,
    ]);

    $html = test()->actingAs($victim)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    // A DIFFERENT approved seller replays the victim's snapshot.
    test()->actingAs(saSeller('Attacker Store'));
    saReplay($snapshot, 'delist', [$product->id]);

    expect($product->fresh()->status)->toBe(ProductStatus::Live);
});

test('a guest replaying a seller snapshot is refused', function () {
    $seller = saSeller();
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);

    $html = test()->actingAs($seller)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    auth()->logout();
    session()->flush();

    $response = saReplay($snapshot, 'delist', [$product->id]);

    // A refusal is anything that is not a successful component update — the
    // middleware answers with a 302 to the branded door rather than a 403, so
    // asserting >=400 would report a real guard as a failure.
    expect($response->status())->not->toBe(200)
        ->and($product->fresh()->status)->toBe(ProductStatus::Live);
});

/**
 * CONTROL — the harness must be able to succeed, or every pass above is
 * vacuous. The owner replaying their OWN snapshot must actually delist.
 */
test('CONTROL: the seller replaying their own snapshot DOES delist', function () {
    $seller = saSeller();
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);

    $html = test()->actingAs($seller)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    saReplay($snapshot, 'delist', [$product->id]);

    expect($product->fresh()->status)->not->toBe(ProductStatus::Live);
});

// ── A seller whose store is suspended mid-session ────────────────────────

test('a seller suspended after render can no longer drive their own snapshot', function () {
    $seller = saSeller();
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);

    $html = test()->actingAs($seller)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    $seller->store->forceFill(['status' => StoreStatus::Suspended])->save();

    saReplay($snapshot, 'delist', [$product->id]);

    expect($product->fresh()->status)->toBe(ProductStatus::Live);
});

/**
 * Which layer actually refuses? Recorded so the mechanism is not re-derived.
 * C8 found EnsureAdmin is NOT re-applied on /livewire/update; this documents
 * what happens on the seller side.
 */
test('the refusal comes from middleware, not from luck downstream', function () {
    $seller = saSeller();
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);

    $html = test()->actingAs($seller)->get('/seller/products')->getContent();
    $snapshot = saSnapshot($html, 'seller.products');

    test()->actingAs(saBuyer());
    $response = saReplay($snapshot, 'delist', [$product->id]);

    // A buyer is bounced to the seller application, i.e. EnsureSeller ran on
    // the update request — the component method was never reached.
    expect($response->headers->get('Location'))->toContain('/seller/apply')
        ->and($product->fresh()->status)->toBe(ProductStatus::Live);
});
