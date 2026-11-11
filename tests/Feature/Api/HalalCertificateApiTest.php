<?php

/**
 * The app shows the halal certificate everywhere the web does (Haze
 * 2026-11-10: "in web got, in here doesnt"). Every block reads
 * Product::halalVerdict(), the one owner of the badge — never re-derived.
 */

use App\Enums\CertificateStatus;
use App\Models\Address;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function hcApiCert(array $attrs = [], CertificateStatus $status = CertificateStatus::Approved): HalalCertificate
{
    $cert = HalalCertificate::create(array_merge([
        'store_id' => Store::factory()->approved()->create()->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-118',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'Department of Islamic Development Malaysia',
        'holder_name' => 'Kedai Madu Sdn Bhd',
        'scheme' => 'MS 1500:2019',
        'scope_note' => 'raw honey, honey blends',
        'facility' => 'Kuala Terengganu plant',
        'dedicated_facility' => true,
        'export_paperwork' => false,
        'valid_from' => now()->subYear(),
        'valid_to' => now()->addYears(2),
    ], $attrs));
    $cert->forceFill(['status' => $status])->save();

    return $cert;
}

function hcApiProduct(?HalalCertificate $cert, array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'store_id' => $cert?->store_id ?? Store::factory()->approved()->create()->id,
        'halal_certificate_id' => $cert?->id,
    ], $attrs));
}

test('product detail carries the certificate panel, scope, traceability and the register link', function () {
    $cert = hcApiCert();
    $product = hcApiProduct($cert, ['halal_batch_code' => 'B-2211', 'halal_packed_on' => '2026-10-01', 'halal_cert_number' => 'MY-JKM-PRINTED-1']);
    $product->metafields()->create(['key' => 'halal_cert_body', 'value' => 'JAKIM']);
    $product->metafields()->create(['key' => 'country_of_origin', 'value' => 'Malaysia']);

    $res = $this->getJson("/api/v1/products/{$product->slug}")->assertOk();

    $res->assertJsonPath('data.halal.verdict', 'verified')
        ->assertJsonPath('data.halal.number', $cert->number)
        ->assertJsonPath('data.halal.issuing_body', 'JAKIM')
        ->assertJsonPath('data.halal.issuing_body_name', 'Department of Islamic Development Malaysia')
        ->assertJsonPath('data.halal.valid_from', $cert->valid_from->toDateString())
        ->assertJsonPath('data.halal.valid_to', $cert->valid_to->toDateString())
        ->assertJsonPath('data.halal.days_remaining', $cert->daysRemaining())
        ->assertJsonPath('data.halal.scheme', 'MS 1500:2019')
        ->assertJsonPath('data.halal.scope_note', 'raw honey, honey blends')
        ->assertJsonPath('data.halal.facility', 'Kuala Terengganu plant')
        ->assertJsonPath('data.halal.dedicated_facility', true)
        ->assertJsonPath('data.halal.export_paperwork', false)
        ->assertJsonPath('data.halal.register_url', route('certificate.register', ['no' => $cert->number]))
        ->assertJsonPath('data.halal_batch_code', 'B-2211')
        ->assertJsonPath('data.halal_packed_on', '2026-10-01')
        ->assertJsonPath('data.halal_cert_number', 'MY-JKM-PRINTED-1')
        ->assertJsonPath('data.metafields.0.key', 'halal')
        ->assertJsonPath('data.metafields.0.label', 'Halal certification')
        ->assertJsonPath('data.metafields.0.rows.0', ['label' => 'Halal certification body', 'value' => 'JAKIM'])
        ->assertJsonPath('data.metafields.1.key', 'details')
        ->assertJsonPath('data.metafields.1.rows.0.value', 'Malaysia');

    // The web box rendered these in English even in BM; the app reads them in Malay.
    $this->withHeader('Accept-Language', 'ms')->getJson("/api/v1/products/{$product->slug}")
        ->assertJsonPath('data.metafields.0.label', 'Pensijilan halal')
        ->assertJsonPath('data.metafields.0.rows.0.label', 'Badan pensijilan halal');
});

test('a lapsed certificate reads lapsed, an unapproved one pending, none at all is null', function () {
    $lapsed = hcApiProduct(hcApiCert(['valid_from' => now()->subYears(3), 'valid_to' => now()->subDay()]));
    $pending = hcApiProduct(hcApiCert([], CertificateStatus::Pending));
    $none = hcApiProduct(null);

    $this->getJson("/api/v1/products/{$lapsed->slug}")->assertJsonPath('data.halal.verdict', 'lapsed');
    $this->getJson("/api/v1/products/{$pending->slug}")->assertJsonPath('data.halal.verdict', 'pending');
    $this->getJson("/api/v1/products/{$none->slug}")
        ->assertJsonPath('data.halal', null)
        ->assertJsonPath('data.metafields', []);
});

test('every product list carries the verdict for the card badge', function () {
    $cert = hcApiCert();
    $product = hcApiProduct($cert, ['name' => ['en' => 'Madu Tualang Asli'], 'sold_count' => 999]);

    $this->getJson('/api/v1/products')->assertJsonPath('data.0.halal.verdict', 'verified')
        ->assertJsonPath('data.0.halal.number', $cert->number);
    $this->getJson("/api/v1/stores/{$product->store->slug}/products")->assertJsonPath('data.0.halal.verdict', 'verified');
    $this->getJson('/api/v1/home')->assertJsonPath('data.featured.0.halal.verdict', 'verified');
});

test('products and search filter by certifying body and assurance, approved certificates only', function () {
    $jakim = hcApiProduct(hcApiCert(['dedicated_facility' => true, 'export_paperwork' => false]));
    $muis = hcApiProduct(hcApiCert([
        'number' => 'SG-MUIS-7001-1', 'issuing_body' => 'MUIS',
        'dedicated_facility' => false, 'export_paperwork' => true, 'valid_to' => now()->addMonths(3),
    ]));
    $pendingJakim = hcApiProduct(hcApiCert([], CertificateStatus::Pending));
    hcApiProduct(null);

    $ids = fn (string $query) => collect($this->getJson('/api/v1/products?'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids('certifier[]=JAKIM'))->toBe([$jakim->id])
        ->and($ids('certifier[]=JAKIM&certifier[]=MUIS'))->toBe(collect([$jakim->id, $muis->id])->sort()->values()->all())
        ->and($ids('assure[]=dedicated'))->toBe([$jakim->id])
        ->and($ids('assure[]=export'))->toBe([$muis->id])
        ->and($ids('assure[]=valid12'))->toBe([$jakim->id])
        ->and($ids('assure[]=dedicated&assure[]=export'))->toBe([])
        ->and($ids(''))->toContain($pendingJakim->id);

    $this->getJson('/api/v1/products?certifier[]=NOPE')->assertUnprocessable();
    $this->getJson('/api/v1/products?assure[]=nope')->assertUnprocessable();
});

test('cart and checkout lines carry the certificate number only when verified', function () {
    $user = User::factory()->create();
    $user->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $user->id, 'state' => 'Selangor']);
    $http = $this->withToken($user->createToken('pest-phone')->plainTextToken);

    $cert = hcApiCert();
    $verified = hcApiProduct($cert, ['cod_enabled' => true]);
    $lapsed = hcApiProduct(hcApiCert(['valid_from' => now()->subYears(3), 'valid_to' => now()->subDay()]), ['cod_enabled' => true]);
    foreach ([$verified, $lapsed] as $p) {
        $p->variants->first()->update(['stock' => 5, 'sale_price_sen' => null]);
        app(CartService::class)->addItem($user, $p->variants->first(), 1);
    }

    $lines = collect($http->getJson('/api/v1/cart')->assertOk()->json('data.groups'))->flatMap(fn ($g) => $g['lines'])->keyBy('product.id');
    expect($lines[$verified->id]['product']['halal_cert_number'])->toBe($cert->number)
        ->and($lines[$lapsed->id]['product']['halal_cert_number'])->toBeNull();

    $quote = collect($http->postJson('/api/v1/checkout/quote', ['address_id' => $address->id])->assertOk()->json('data.groups'))
        ->flatMap(fn ($g) => $g['lines'])->keyBy('product_id');
    expect($quote[$verified->id]['halal_cert_number'])->toBe($cert->number)
        ->and($quote[$lapsed->id]['halal_cert_number'])->toBeNull();
});

test('GET certificates/{number} is public and carries exactly the register page record', function () {
    $cert = hcApiCert();
    $cert->events()->create(['occurred_on' => '2026-01-05', 'summary' => 'Surveillance audit passed']);
    hcApiProduct($cert);

    $res = $this->getJson('/api/v1/certificates/'.rawurlencode(strtolower(substr($cert->number, 0, 6)).' '.substr($cert->number, 6)))
        ->assertOk()
        ->assertJsonPath('data.number', $cert->number)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.holder_name', 'Kedai Madu Sdn Bhd')
        ->assertJsonPath('data.products_count', 1)
        ->assertJsonPath('data.events.0.summary', 'Surveillance audit passed');

    // Nothing beyond what the web register shows: no ids, reviewer, notes, documents, status.
    expect(array_keys($res->json('data')))->toEqualCanonicalizing([
        'number', 'valid', 'days_remaining', 'issuing_body', 'issuing_body_name', 'holder_name', 'holder_state', 'scheme',
        'scope_note', 'valid_from', 'valid_to', 'facility', 'products_count', 'dedicated_facility', 'export_paperwork',
        'store_slug', 'events',
    ]);
    expect(array_keys($res->json('data.events.0')))->toEqual(['occurred_on', 'summary']);
});

test('GET certificates/{number} is 404 for an unknown number and for one not yet approved', function () {
    $this->getJson('/api/v1/certificates/NOPE-0000')->assertNotFound();
    $pending = hcApiCert([], CertificateStatus::Pending);
    $this->getJson("/api/v1/certificates/{$pending->number}")->assertNotFound();
});
