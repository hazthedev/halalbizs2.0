<?php

/**
 * QA — the halal verdict has exactly ONE owner.
 *
 * Reported 2026-08-07: the PDP printed "HALAL CERTIFICATE VERIFIED · valid to
 * 7 Jan 2029" for a certificate the register called "CERTIFICATE NOT VALID",
 * because the badge derived its answer from `valid_to` alone while the register
 * called HalalCertificate::isValid(), which checks BOTH bounds. 17 of 24
 * certificates on the preview were in that state, badging 134 of 166 products.
 */

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;

function hvCert(array $attrs = []): HalalCertificate
{
    return HalalCertificate::create(array_merge([
        'store_id' => Store::factory()->create()->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-100',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'QA Holder',
        'valid_from' => now()->subYear(),
        'valid_to' => now()->addYear(),
    ], $attrs));
}

it('calls a certificate verified only when it is in force', function () {
    $product = Product::factory()->create(['halal_certificate_id' => hvCert()->id]);

    expect($product->halalVerdict())->toBe('verified');
});

it('does NOT call a not-yet-started certificate verified', function () {
    $cert = hvCert(['valid_from' => now()->addMonths(6), 'valid_to' => now()->addYears(3)]);
    $product = Product::factory()->create(['halal_certificate_id' => $cert->id]);

    expect($cert->isValid())->toBeFalse()          // the register's answer
        ->and($product->halalVerdict())->toBe('pending'); // and now the badge agrees
});

it('calls a lapsed certificate lapsed', function () {
    $cert = hvCert(['valid_from' => now()->subYears(3), 'valid_to' => now()->subDay()]);
    $product = Product::factory()->create(['halal_certificate_id' => $cert->id]);

    expect($product->halalVerdict())->toBe('lapsed');
});

/**
 * Fails CLOSED. The old badge keyed off `halal_cert_number`, a free-text string
 * on the product row, so anything typed there rendered as verified — and a null
 * expiry made `lapsed` false, i.e. a green tick with no date at all.
 */
it('refuses to verify a bare certificate NUMBER with no record behind it', function () {
    $product = Product::factory()->create([
        'halal_cert_number' => 'MY-JKM-9999-999',
        'halal_cert_expiry' => null,
        'halal_certificate_id' => null,
    ]);

    expect($product->halalVerdict())->toBe('unverified');
});

it('the badge and the register never disagree, across every window', function (string $label, $from, $to, string $expected) {
    $cert = hvCert(['valid_from' => $from, 'valid_to' => $to]);
    $product = Product::factory()->create(['halal_certificate_id' => $cert->id]);

    expect($product->halalVerdict())->toBe($expected)
        ->and($cert->isValid())->toBe($expected === 'verified');
})->with([
    ['in force', now()->subDay(), now()->addYear(), 'verified'],
    ['starts today', now()->startOfDay(), now()->addYear(), 'verified'],
    ['expires today', now()->subYear(), now()->startOfDay(), 'verified'],
    ['not yet started', now()->addDay(), now()->addYear(), 'pending'],
    ['expired yesterday', now()->subYear(), now()->subDay(), 'lapsed'],
]);

/**
 * The page must actually RENDER. The whole 10-fix batch passed 1107/1107 with
 * the product page 500ing on the preview: replacing the badge's @php block
 * removed $certExpiry, which a traceability row further down the same blade
 * still used, and nothing in the suite rendered that section. A green suite is
 * exactly what a broken page looks like from the inside.
 */
it('renders the product page for every certificate state', function (string $label, $from, $to) {
    $cert = hvCert(['valid_from' => $from, 'valid_to' => $to]);
    $product = Product::factory()->create([
        'halal_certificate_id' => $cert->id,
        'status' => ProductStatus::Live,
    ]);
    $product->store->forceFill(['status' => StoreStatus::Approved])->save();

    test()->get('/p/'.$product->slug)->assertOk();
})->with([
    ['in force', now()->subDay(), now()->addYear()],
    ['not yet started', now()->addDay(), now()->addYear()],
    ['expired', now()->subYears(2), now()->subDay()],
]);

it('renders the product page when there is no certificate at all', function () {
    $product = Product::factory()->create([
        'halal_certificate_id' => null,
        'halal_cert_number' => 'MY-JKM-0000-000',
        'status' => ProductStatus::Live,
    ]);
    $product->store->forceFill(['status' => StoreStatus::Approved])->save();

    test()->get('/p/'.$product->slug)
        ->assertOk()
        ->assertDontSee('Halal certificate verified');
});
