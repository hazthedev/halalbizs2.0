<?php

use App\Enums\ProductStatus;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\HalalCertificateWatch;
use Illuminate\Support\Facades\Notification;

/**
 * The expiry watch. A product on sale under an EXPIRED halal certificate is the
 * worst thing this catalogue can do, and until 2026-08-10 nothing checked.
 */
// No HalalCertificateFactory exists; HalalVerdictTest builds them the same way,
// so follow the neighbour rather than introduce a factory for one file.
function watchCert(array $attrs = []): HalalCertificate
{
    $store = Store::factory()->approved()->create(['user_id' => User::factory()->create()->id]);

    return HalalCertificate::create(array_merge([
        'store_id' => $store->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-100',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Watch Holder',
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => now()->addYear()->toDateString(),
    ], $attrs));
}

function watchProduct(HalalCertificate $cert, ProductStatus $status = ProductStatus::Live): Product
{
    return Product::factory()->create([
        'store_id' => $cert->store_id,
        'halal_certificate_id' => $cert->id,
        'status' => $status,
    ]);
}

it('delists live products the day their certificate expires, and tells the seller', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->subDay()->toDateString()]);
    $product = watchProduct($cert);

    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($product->fresh()->status)->toBe(ProductStatus::Delisted)
        ->and($product->fresh()->halal_delisted_at)->not->toBeNull();

    Notification::assertSentTo($cert->store->user, HalalCertificateWatch::class,
        fn ($n) => $n->state === 'lapsed' && $n->affectedProducts === 1);
});

it('leaves products alone while the certificate is still valid', function () {
    Notification::fake();

    $product = watchProduct(watchCert());

    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($product->fresh()->status)->toBe(ProductStatus::Live)
        ->and($product->fresh()->halal_delisted_at)->toBeNull();
});

it('restores what the watch delisted once the certificate is renewed', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->subDay()->toDateString()]);
    $product = watchProduct($cert);

    $this->artisan('certificates:watch-expiry');
    expect($product->fresh()->status)->toBe(ProductStatus::Delisted);

    // Seller renews.
    $cert->forceFill(['valid_to' => now()->addYear()->toDateString()])->save();
    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($product->fresh()->status)->toBe(ProductStatus::Live)
        ->and($product->fresh()->halal_delisted_at)->toBeNull();
});

/**
 * The reason `halal_delisted_at` exists. Without it, "restore on renewal" means
 * re-listing every delisted product of that store — including stock the seller
 * pulled on purpose. Putting withdrawn stock back on sale is worse than leaving
 * a renewal un-restored.
 */
it('does NOT restore a product the seller delisted themselves', function () {
    Notification::fake();

    $cert = watchCert();
    $sellerDelisted = watchProduct($cert, ProductStatus::Delisted);

    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($sellerDelisted->fresh()->status)->toBe(ProductStatus::Delisted);
});

it('nudges the seller inside the renewal window, exactly once', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->addDays(30)->toDateString()]);
    watchProduct($cert);

    $this->artisan('certificates:watch-expiry')->assertSuccessful();
    Notification::assertSentToTimes($cert->store->user, HalalCertificateWatch::class, 1);

    // Daily schedule: a second run must not warn again.
    $this->artisan('certificates:watch-expiry')->assertSuccessful();
    Notification::assertSentToTimes($cert->store->user, HalalCertificateWatch::class, 1);

    expect($cert->fresh()->renewal_notified_at)->not->toBeNull();
});

it('does not nudge for a certificate outside the window', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->addDays(200)->toDateString()]);

    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    Notification::assertNothingSentTo($cert->store->user);
    expect($cert->fresh()->renewal_notified_at)->toBeNull();
});

it('can nudge again after a renewal pushes the date back out', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->addDays(30)->toDateString()]);
    $this->artisan('certificates:watch-expiry');
    expect($cert->fresh()->renewal_notified_at)->not->toBeNull();

    $cert->forceFill(['valid_to' => now()->addYears(2)->toDateString()])->save();
    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    // Marker cleared, so the next expiry cycle warns afresh rather than staying
    // silent forever because of a nudge sent two years ago.
    expect($cert->fresh()->renewal_notified_at)->toBeNull();
});

it('is idempotent — a second run changes nothing and re-notifies nobody', function () {
    Notification::fake();

    $cert = watchCert(['valid_to' => now()->subDay()->toDateString()]);
    $product = watchProduct($cert);

    $this->artisan('certificates:watch-expiry');
    $stamp = $product->fresh()->halal_delisted_at;

    $this->artisan('certificates:watch-expiry')->assertSuccessful();

    expect($product->fresh()->halal_delisted_at->eq($stamp))->toBeTrue();
    Notification::assertSentToTimes($cert->store->user, HalalCertificateWatch::class, 1);
});
