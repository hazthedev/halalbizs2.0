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

use App\Enums\CertificateStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;

function hvCert(array $attrs = []): HalalCertificate
{
    $cert = HalalCertificate::create(array_merge([
        'store_id' => Store::factory()->create()->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-100',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'QA Holder',
        'valid_from' => now()->subYear(),
        'valid_to' => now()->addYear(),
    ], $attrs));

    // These cases are about the DATE window, not the review state, so the
    // fixture has to say which one it is. Since H-6 a certificate is created
    // Pending by default and only an approved one can badge a product — this
    // file predates that, and every case here means "an approved certificate,
    // dated like so".
    $cert->forceFill(['status' => CertificateStatus::Approved])->save();

    return $cert;
}

/**
 * A product covered by $cert, IN THE SAME STORE.
 *
 * It used to be a bare Product::factory(), which mints its own store — so every
 * case here was quietly binding one shop's certificate to another shop's
 * product. Nothing enforced it until the H-6 binding guard landed, and then it
 * turned out our own fixtures were doing the exact thing the audit warned a UI
 * would eventually let a seller do.
 */
function hvProduct(HalalCertificate $cert, array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'store_id' => $cert->store_id,
        'halal_certificate_id' => $cert->id,
    ], $attrs));
}

it('calls a certificate verified only when it is in force', function () {
    $product = hvProduct(hvCert());

    expect($product->halalVerdict())->toBe('verified');
});

it('does NOT call a not-yet-started certificate verified', function () {
    $cert = hvCert(['valid_from' => now()->addMonths(6), 'valid_to' => now()->addYears(3)]);
    $product = hvProduct($cert);

    expect($cert->isValid())->toBeFalse()          // the register's answer
        ->and($product->halalVerdict())->toBe('pending'); // and now the badge agrees
});

it('calls a lapsed certificate lapsed', function () {
    $cert = hvCert(['valid_from' => now()->subYears(3), 'valid_to' => now()->subDay()]);
    $product = hvProduct($cert);

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
    $product = hvProduct($cert);

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
    $product = hvProduct($cert, ['status' => ProductStatus::Live]);
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
