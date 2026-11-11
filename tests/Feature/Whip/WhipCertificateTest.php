<?php

use App\Enums\CertificateStatus;
use App\Enums\ProductStatus;
use App\Livewire\Seller\Certificates;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// WHIP is read through a second connection. Tests point it at in-memory SQLite
// and create only the columns the directory reads.

beforeEach(function () {
    $s = Schema::connection('whip');
    $s->create('halal_applications', function ($t) {
        $t->id();
        $t->unsignedBigInteger('certification_body_id')->default(1);
        $t->string('status');
    });
    $s->create('halal_application_certificates', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('certificate_no')->nullable();
        $t->date('valid_from')->nullable();
        $t->date('valid_to')->nullable();
    });
    $s->create('halal_application_company_profiles', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('company_name')->nullable();
        $t->string('registration_number')->nullable();
    });
    $s->create('halal_application_products', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('product_name');
        $t->string('barcode')->nullable();
    });
    $s->create('certification_bodies', function ($t) {
        $t->id();
        $t->string('name');
    });
    DB::connection('whip')->table('certification_bodies')->insert(['id' => 1, 'name' => 'Test CB']);
});

/** One WHIP application with certificate + company; overrides per key. */
function whipCert(array $o = []): int
{
    $w = DB::connection('whip');
    $app = $w->table('halal_applications')->insertGetId(['status' => $o['status'] ?? 'certified']);
    $w->table('halal_application_certificates')->insert([
        'application_id' => $app,
        'certificate_no' => array_key_exists('no', $o) ? $o['no'] : 'WH-100',
        'valid_from' => $o['from'] ?? now()->subMonth()->toDateString(),
        'valid_to' => $o['to'] ?? now()->addYear()->toDateString(),
    ]);
    $w->table('halal_application_company_profiles')->insert([
        'application_id' => $app, 'company_name' => 'WHIP Foods Sdn Bhd', 'registration_number' => $o['reg'] ?? '202001-000123',
    ]);
    $w->table('halal_application_products')->insert(['application_id' => $app, 'product_name' => 'Kicap', 'barcode' => '955000']);

    return $app;
}

function whipSeller(?string $reg = '202001000123'): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id, 'registration_no' => $reg]);

    return $user->fresh();
}

function whipSubmit(User $seller, string $number = 'WH-100'): void
{
    Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate')
        ->set('number', $number)->set('issuingBody', 'JAKIM')->set('holderName', 'Typed Name')
        ->set('validFrom', now()->subMonth()->toDateString())->set('validTo', now()->addYear()->toDateString())
        ->set('document', UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'))
        ->call('save')->assertHasNoErrors();
}

function whipRow(string $number = 'WH-100'): HalalCertificate
{
    return HalalCertificate::where('number', $number)->firstOrFail();
}

test('a certificate number that WHIP holds as certified and in date, for the same company registration number, is verified from WHIP', function () {
    $app = whipCert();
    whipSubmit(whipSeller());

    $c = whipRow();
    expect($c->status)->toBe(CertificateStatus::Approved)
        ->and($c->source)->toBe('whip')
        ->and($c->whip_application_id)->toBe($app)
        ->and($c->whip_synced_at)->not->toBeNull()
        ->and($c->holder_name)->toBe('WHIP Foods Sdn Bhd')
        ->and($c->issuing_body_name)->toBe('Test CB');
});

test('active in WHIP but another registration number goes to manual review', function () {
    whipCert(['reg' => '999999-X']);
    whipSubmit(whipSeller());

    expect(whipRow())->status->toBe(CertificateStatus::Pending)->source->toBe('manual')->whip_answer->toBe('mismatch');
});

test('a store with no registration number never auto-verifies', function () {
    whipCert();
    whipSubmit(whipSeller(null));

    expect(whipRow())->status->toBe(CertificateStatus::Pending);
});

test('an expired WHIP certificate goes to manual review and says expired', function () {
    whipCert(['from' => now()->subYears(3)->toDateString(), 'to' => now()->subDay()->toDateString()]);
    whipSubmit(whipSeller());

    $c = whipRow();
    expect($c->status)->toBe(CertificateStatus::Pending)->and($c->whip_answer)->toBe('expired')
        ->and($c->whipNote())->toContain('expired');
});

test('a number WHIP does not hold goes to manual review', function () {
    whipSubmit(whipSeller(), 'NOPE-1');

    expect(whipRow('NOPE-1'))->status->toBe(CertificateStatus::Pending)->whip_answer->toBe('not_found');
});

test('WHIP rows with an empty certificate number never match', function () {
    whipCert(['no' => '']);
    whipCert(['no' => null]);

    expect(app(\App\Services\Whip\WhipDirectory::class)->lookup('  '))->toBeNull();
});

test('number match ignores case and spaces around it, and a not-certified application is not active', function () {
    whipCert(['no' => ' wh-100 ', 'status' => 'Under Review']);

    expect(app(\App\Services\Whip\WhipDirectory::class)->lookup('WH-100')->status)->toBe('not_certified');
});

test('two active records with one number are ambiguous, not verified', function () {
    whipCert();
    whipCert();
    whipSubmit(whipSeller());

    expect(whipRow())->status->toBe(CertificateStatus::Pending)->whip_answer->toBe('ambiguous');
});

test('the sync moves a certificate WHIP now holds as expired to expired and delists its products', function () {
    $app = whipCert();
    whipSubmit(whipSeller());
    $c = whipRow();
    $product = Product::factory()->create(['store_id' => $c->store_id, 'status' => ProductStatus::Live, 'halal_certificate_id' => $c->id]);

    DB::connection('whip')->table('halal_application_certificates')->where('application_id', $app)
        ->update(['valid_to' => now()->subDay()->toDateString()]);

    $this->artisan('certificates:sync-whip')->assertSuccessful();
    $this->artisan('certificates:sync-whip')->assertSuccessful(); // idempotent

    expect($c->fresh()->valid_to->isPast())->toBeTrue()
        ->and($c->fresh()->whip_answer)->toBe('expired')
        ->and($product->fresh()->halalVerdict())->toBe('lapsed')
        ->and($product->fresh()->status)->toBe(ProductStatus::Delisted)
        ->and($c->events()->count())->toBe(2);
});

test('the sync rejects a certificate WHIP no longer certifies', function () {
    $app = whipCert();
    whipSubmit(whipSeller());
    DB::connection('whip')->table('halal_applications')->where('id', $app)->update(['status' => 'Rejected']);

    $this->artisan('certificates:sync-whip')->assertSuccessful();

    expect(whipRow())->status->toBe(CertificateStatus::Rejected)->whip_answer->toBe('not_certified');
});

test('when WHIP is down the sync changes nothing', function () {
    whipCert();
    whipSubmit(whipSeller());
    $before = whipRow()->only(['status', 'valid_to', 'whip_synced_at', 'whip_answer']);

    Schema::connection('whip')->drop('halal_application_certificates'); // WHIP query now fails

    $this->artisan('certificates:sync-whip')->assertFailed();

    expect(whipRow()->only(['status', 'valid_to', 'whip_synced_at', 'whip_answer']))->toEqual($before);
});

test('a WHIP certificate not synced for over 24 hours stops counting as verified, but nothing is delisted', function () {
    whipCert();
    whipSubmit(whipSeller());
    $c = whipRow();
    $product = Product::factory()->create(['store_id' => $c->store_id, 'status' => ProductStatus::Live, 'halal_certificate_id' => $c->id]);

    expect($product->fresh()->halalVerdict())->toBe('verified');

    $this->travel(25)->hours();

    expect($product->fresh()->halalVerdict())->toBe('pending')
        ->and($product->fresh()->status)->toBe(ProductStatus::Live);
});
