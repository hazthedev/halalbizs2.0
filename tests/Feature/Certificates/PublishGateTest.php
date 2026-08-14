<?php

use App\Enums\CertificateStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Catalog\Moderation;
use App\Livewire\Seller\Products\Index as SellerProducts;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ProductPublishPolicy;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Audit H-6, the enforcement half: gate food, badge the rest.
//
// The three valves all exist so OUR latency never costs a seller a live
// listing: the enforced-from date, the review grace, and the same grace on a
// renewal (without which the 90-day nudge tells sellers to do the thing that
// costs them their badge).

beforeEach(function () {
    // Enforcing, unless a test says otherwise. The shipped default is OFF.
    config(['halal.gate_enforced_from' => now()->subDay()->toDateString()]);
});

function gatedCategory(?bool $requires = true, ?Category $parent = null): Category
{
    return Category::factory()->create([
        'parent_id' => $parent?->id,
        'requires_halal_certificate' => $requires,
    ]);
}

function gatedProduct(Category $category, ?HalalCertificate $cert = null): Product
{
    return Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $cert?->store_id ?? Store::factory()->approved()->create()->id,
        'halal_certificate_id' => $cert?->id,
    ]);
}

function gateCert(int $storeId, CertificateStatus $status = CertificateStatus::Approved, array $attrs = []): HalalCertificate
{
    $cert = HalalCertificate::create(array_merge([
        'store_id' => $storeId,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-400',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Gate Holder',
        'valid_from' => now()->subMonth(),
        'valid_to' => now()->addYear(),
    ], $attrs));

    $cert->forceFill(['status' => $status])->save();

    return $cert;
}

test('an uncertified product in a gated category cannot go live', function () {
    $product = gatedProduct(gatedCategory());

    expect(app(ProductPublishPolicy::class)->allows($product))->toBeFalse()
        ->and(app(ProductPublishPolicy::class)->blockedReason($product))
        ->toContain('needs a halal certificate');
});

test('a product outside a gated category is untouched', function () {
    // A prayer mat needs no certificate and must not be blocked by one.
    expect(app(ProductPublishPolicy::class)->allows(gatedProduct(gatedCategory(null))))->toBeTrue()
        ->and(app(ProductPublishPolicy::class)->allows(gatedProduct(gatedCategory(false))))->toBeTrue();
});

test('an approved certificate opens the gate', function () {
    $store = Store::factory()->approved()->create();
    $product = gatedProduct(gatedCategory(), gateCert($store->id));

    expect(app(ProductPublishPolicy::class)->allows($product))->toBeTrue();
});

// The inheritance is the reason the flag is nullable: set it once on Groceries
// & Pantry, every leaf under it inherits, and a single node can override.
test('the flag is inherited from the nearest flagged ancestor', function () {
    $root = gatedCategory(true);
    $mid = gatedCategory(null, $root);
    $leaf = gatedCategory(null, $mid);

    expect(app(ProductPublishPolicy::class)->allows(gatedProduct($leaf)))->toBeFalse();

    // ...and a single node can opt its branch back out.
    $exempt = gatedCategory(false, $mid);
    expect(app(ProductPublishPolicy::class)->allows(gatedProduct($exempt)))->toBeTrue();
});

// ── Valve 1 ──────────────────────────────────────────────────────────────
test('the gate reports and does not block until its date arrives', function () {
    $product = gatedProduct(gatedCategory());

    config(['halal.gate_enforced_from' => null]);
    expect(app(ProductPublishPolicy::class)->allows($product))->toBeTrue();

    config(['halal.gate_enforced_from' => now()->addMonth()->toDateString()]);
    expect(app(ProductPublishPolicy::class)->allows($product))->toBeTrue();

    config(['halal.gate_enforced_from' => now()->subDay()->toDateString()]);
    expect(app(ProductPublishPolicy::class)->allows($product))->toBeFalse();
});

// ── Valves 2 and 3 ───────────────────────────────────────────────────────
test('a submitted certificate holds the line while we review it', function () {
    $store = Store::factory()->approved()->create();
    $cert = gateCert($store->id, CertificateStatus::Pending);
    $cert->forceFill(['submitted_at' => now()->subDay()])->save();

    expect(app(ProductPublishPolicy::class)->allows(gatedProduct(gatedCategory(), $cert)))->toBeTrue();
});

test('the grace runs out, so an abandoned submission cannot hold a listing open', function () {
    $store = Store::factory()->approved()->create();
    $cert = gateCert($store->id, CertificateStatus::Pending);
    $cert->forceFill(['submitted_at' => now()->subDays(ProductPublishPolicy::REVIEW_GRACE_DAYS + 1)])->save();

    expect(app(ProductPublishPolicy::class)->allows(gatedProduct(gatedCategory(), $cert)))->toBeFalse();
});

// ⚠ The one that makes the 90-day renewal nudge honest. Renewing sends the
// record back to Pending; without this, renewing EARLY costs the badge early,
// so no rational seller would ever do it.
test('renewing early does not cost the seller their listing', function () {
    $store = Store::factory()->approved()->create();
    $cert = gateCert($store->id);
    $product = gatedProduct(gatedCategory(), $cert);

    expect(app(ProductPublishPolicy::class)->allows($product))->toBeTrue();

    // The seller renews, exactly as Seller\Certificates::save() does.
    $cert->forceFill(['status' => CertificateStatus::Pending, 'submitted_at' => now()])->save();

    expect(app(ProductPublishPolicy::class)->allows($product->fresh()))->toBeTrue();
});

test('an expired certificate blocks once its grace is spent', function () {
    $store = Store::factory()->approved()->create();
    $cert = gateCert($store->id, CertificateStatus::Approved, [
        'valid_from' => now()->subYears(2),
        'valid_to' => now()->subMonth(),
    ]);

    expect(app(ProductPublishPolicy::class)->blockedReason(gatedProduct(gatedCategory(), $cert)))
        ->toContain('expired');
});

// ── Every writer of Live goes through the policy ─────────────────────────
test('the expiry sweep will not restore a listing on an unapproved certificate', function () {
    $store = Store::factory()->approved()->create();
    $cert = gateCert($store->id, CertificateStatus::Pending);

    $product = Product::factory()->create([
        'store_id' => $store->id,
        'halal_certificate_id' => $cert->id,
        'status' => ProductStatus::Delisted,
        'halal_delisted_at' => now()->subDay(),
    ]);

    test()->artisan('certificates:watch-expiry')->assertSuccessful();

    // The grace covers the seller during review; this sweep is what makes a
    // restore permanent, so it waits for the actual approval.
    expect($product->fresh()->status)->toBe(ProductStatus::Delisted);

    $cert->forceFill(['status' => CertificateStatus::Approved])->save();
    test()->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($product->fresh()->status)->toBe(ProductStatus::Live);
});

// ── The call sites ───────────────────────────────────────────────────────
// The policy passing proves nothing on its own: four places write
// ProductStatus::Live and the bug this replaces was that they each decided for
// themselves. These assert the gate is actually reached from each of them.

test('the seller cannot relist a gated product from the products list', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);

    $product = Product::factory()->create([
        'store_id' => $store->id,
        'category_id' => gatedCategory()->id,
        'status' => ProductStatus::Delisted,
    ]);

    Livewire::actingAs($seller->fresh())->test(SellerProducts::class)->call('relist', $product->id);

    expect($product->fresh()->status)->toBe(ProductStatus::Delisted);
});

// The route that BYPASSES the seller's screen entirely — if the gate is not
// here, an admin approval is a way around it.
test('admin moderation will not approve a gated product either', function () {
    (new RoleSeeder)->run();
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    $blocked = Product::factory()->create([
        'category_id' => gatedCategory()->id,
        'status' => ProductStatus::PendingReview,
        'published_at' => null,
    ]);

    $store = Store::factory()->approved()->create();
    $allowed = Product::factory()->create([
        'store_id' => $store->id,
        'category_id' => gatedCategory()->id,
        'halal_certificate_id' => gateCert($store->id)->id,
        'status' => ProductStatus::PendingReview,
        'published_at' => null,
    ]);

    Livewire::actingAs($admin)->test(Moderation::class)
        ->call('approve', $blocked->id)
        ->call('approve', $allowed->id);

    expect($blocked->fresh()->status)->toBe(ProductStatus::PendingReview)
        ->and($allowed->fresh()->status)->toBe(ProductStatus::Live);
});
