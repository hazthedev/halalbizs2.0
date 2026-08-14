<?php

use App\Enums\CertificateStatus;
use App\Livewire\Storefront\CertificateRegister;
use App\Livewire\Storefront\Listing;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use Livewire\Livewire;

// Audit H-6, foundation. The halal layer had no write path — halal_certificates
// was populated by two demo seeders and nothing else — so it never needed a
// review state. Adding one has to not un-verify the shop that already exists.
//
// No HalalCertificateFactory: HalalVerdictTest and ExpiryWatchTest both build
// them inline and the suite's convention is to follow the neighbour.
function lifecycleCert(array $attrs = []): HalalCertificate
{
    // status is not fillable — approval is the trust claim, so it moves only
    // through explicit writes. Tests have to be explicit about it too.
    $status = $attrs['status'] ?? CertificateStatus::Approved;
    unset($attrs['status']);

    $cert = HalalCertificate::create(array_merge([
        'store_id' => Store::factory()->create()->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-200',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Lifecycle Holder',
        'valid_from' => now()->subYear(),
        'valid_to' => now()->addYear(),
    ], $attrs));

    $cert->forceFill(['status' => $status])->save();

    return $cert;
}

// ⚠ The one that matters at deploy. Every existing row IS the live catalogue,
// so the column's default decides whether the storefront keeps its badges.
test('an existing certificate is approved, not pending', function () {
    expect(lifecycleCert()->status)->toBe(CertificateStatus::Approved)
        ->and(lifecycleCert()->isApproved())->toBeTrue();
});

test('a certificate awaiting review does not badge a product as verified', function () {
    $cert = lifecycleCert();
    $product = Product::factory()->create(['halal_certificate_id' => $cert->id]);

    expect($product->halalVerdict())->toBe('verified');

    $cert->forceFill(['status' => CertificateStatus::Pending])->save();

    // Fails CLOSED. Binding only offers approved certificates, so this should be
    // unreachable — which is the reason to assert it.
    expect($product->fresh()->halalVerdict())->toBe('pending');
});

test('a rejected certificate does not badge a product either', function () {
    $cert = lifecycleCert(['status' => CertificateStatus::Rejected]);
    $product = Product::factory()->create(['halal_certificate_id' => $cert->id]);

    expect($product->halalVerdict())->toBe('pending');
});

test('the public register will not publish an unreviewed claim', function () {
    $approved = lifecycleCert();
    $pending = lifecycleCert(['status' => CertificateStatus::Pending, 'holder_name' => 'Unreviewed Holder']);

    Livewire::test(CertificateRegister::class)
        ->set('number', $approved->number)
        ->assertSee('Lifecycle Holder');

    // The register renders validity dates and assurance pills — publishing a
    // submitted-but-unchecked certificate there states it as fact.
    Livewire::test(CertificateRegister::class)
        ->set('number', $pending->number)
        ->assertDontSee('Unreviewed Holder');
});

// The certifier facet was the last reader of products.halal_cert_number, the
// free-text column halalVerdict()'s docblock names as the original bug.
test('the certifier facet reads the certificate record, not the free-text column', function () {
    $real = lifecycleCert(['issuing_body' => 'JAKIM']);
    $backed = Product::factory()->create([
        'halal_certificate_id' => $real->id,
        'halal_cert_number' => $real->number,
    ]);

    // A seller can type anything into halal_cert_number. Before this it was
    // enough to appear under the JAKIM facet with no certificate behind it.
    $claimed = Product::factory()->create([
        'halal_certificate_id' => null,
        'halal_cert_number' => 'MY-JKM-9999-999',
    ]);

    $ids = Livewire::test(Listing::class)
        ->set('certifiers', ['JAKIM'])
        ->viewData('products')
        ->pluck('id');

    expect($ids)->toContain($backed->id)
        ->and($ids)->not->toContain($claimed->id);
});

test('the recognised bodies are defined in exactly one place', function () {
    expect(Listing::certifierCodes())->toBe(array_keys(HalalCertificate::BODIES));
});

// JAKIM requires a renewal application at least three months before expiry, so
// a 60-day nudge landed after the window to act had closed.
test('the renewal nudge window is 90 days and lives in one place', function () {
    expect(HalalCertificate::RENEWAL_WINDOW_DAYS)->toBe(90)
        ->and(lifecycleCert(['valid_to' => now()->addDays(80)])->isExpiringSoon())->toBeTrue()
        ->and(lifecycleCert(['valid_to' => now()->addDays(120)])->isExpiringSoon())->toBeFalse();
});
