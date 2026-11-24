<?php

use App\Enums\CertificateStatus;
use App\Livewire\Seller\Certificates;
use App\Livewire\Seller\Certificates\Form;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// Audit H-6, the write path. Before this screen, halal_certificates was written
// by two demo seeders and nothing else — at a real cutover the whole trust
// layer was empty with no way to fill it.

function certSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user->fresh();
}

function certForm(Testable $component): Testable
{
    return $component
        ->set('number', 'MY-JKM-4242-100')
        ->set('issuingBody', 'JAKIM')
        ->set('holderName', 'Test Foods Sdn Bhd')
        ->set('validFrom', now()->subMonth()->toDateString())
        ->set('validTo', now()->addYear()->toDateString())
        ->set('document', UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'));
}

test('a seller can register a certificate, and it arrives awaiting review', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Form::class))
        ->call('save')
        ->assertHasNoErrors();

    $certificate = HalalCertificate::first();

    // Pending, not approved: a submission is a claim. The seller cannot smuggle
    // an approved status in, because status is not fillable.
    expect($certificate->status)->toBe(CertificateStatus::Pending)
        ->and($certificate->store_id)->toBe($seller->store->id)
        ->and($certificate->submitted_at)->not->toBeNull()
        ->and($certificate->getFirstMedia('document'))->not->toBeNull();
});

test('the uploaded scan goes to the private disk, not the public one', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Form::class))->call('save');

    // Spatie's default disk is the WEB-SERVED public one — no config/media-library.php
    // is published and MEDIA_DISK is unset — so this is the assertion that
    // stops certificate scans landing on a guessable URL.
    expect(HalalCertificate::first()->getFirstMedia('document')->disk)->toBe('local');
});

test('a certificate covers only the products the seller ticks', function () {
    $seller = certSeller();
    $mine = Product::factory()->create(['store_id' => $seller->store->id]);
    $alsoMine = Product::factory()->create(['store_id' => $seller->store->id]);

    certForm(Livewire::actingAs($seller)->test(Form::class))
        ->set('covered', [$mine->id])
        ->call('save');

    $certificate = HalalCertificate::first();

    expect($mine->fresh()->halal_certificate_id)->toBe($certificate->id)
        ->and($alsoMine->fresh()->halal_certificate_id)->toBeNull();
});

// ⚠ The hole the audit named by name: halal_certificate_id is fillable with no
// validation anywhere, so the day a UI shipped without this check, seller A
// could cite seller B's JAKIM certificate.
test('a seller cannot bind another store SKU by posting its id', function () {
    $seller = certSeller();
    $stranger = Product::factory()->create(['store_id' => Store::factory()->approved()->create()->id]);

    certForm(Livewire::actingAs($seller)->test(Form::class))
        ->set('covered', [$stranger->id])
        ->call('save');

    expect($stranger->fresh()->halal_certificate_id)->toBeNull();
});

test('the model refuses a certificate belonging to another store', function () {
    $seller = certSeller();
    $theirs = HalalCertificate::create([
        'store_id' => Store::factory()->approved()->create()->id,
        'number' => 'MY-JKM-9001-001', 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Someone Else', 'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);
    $mine = Product::factory()->create(['store_id' => $seller->store->id]);

    // Enforced on the MODEL, so the importer, a seeder and any future writer
    // hit it too — not just the form above.
    expect(fn () => $mine->update(['halal_certificate_id' => $theirs->id]))
        ->toThrow(RuntimeException::class);
});

test('a renewal edits the same record rather than making a second one', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Form::class))->call('save');

    $certificate = HalalCertificate::first();
    $certificate->forceFill(['status' => CertificateStatus::Approved])->save();

    Livewire::actingAs($seller)->test(Form::class, ['certificate' => $certificate])
        ->set('validTo', now()->addYears(2)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    // One row per printed number — the number is uniquely indexed and the
    // public register looks a certificate up BY it.
    expect(HalalCertificate::count())->toBe(1)
        ->and($certificate->fresh()->status)->toBe(CertificateStatus::Pending)
        ->and($certificate->fresh()->valid_to->toDateString())->toBe(now()->addYears(2)->toDateString())
        ->and($certificate->events()->count())->toBe(2);
});

test('a number already registered to someone else is a clear error, not a 500', function () {
    certSeller();
    HalalCertificate::create([
        'store_id' => Store::factory()->approved()->create()->id,
        'number' => 'MY-JKM-4242-100', 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'First Claimant', 'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);

    certForm(Livewire::actingAs(certSeller())->test(Form::class))
        ->call('save')
        ->assertHasErrors(['number']);
});

test('a term longer than five years is rejected as a typo', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Form::class))
        ->set('validTo', now()->addYears(9)->toDateString())
        ->call('save')
        ->assertHasErrors(['validTo']);

    expect(HalalCertificate::count())->toBe(0);
});

test('a seller cannot see or edit another store certificate', function () {
    $seller = certSeller();
    $theirs = HalalCertificate::create([
        'store_id' => Store::factory()->approved()->create()->id,
        'number' => 'MY-JKM-7777-777', 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Not Yours', 'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);

    Livewire::actingAs($seller)->test(Certificates::class)
        ->assertDontSee('MY-JKM-7777-777');

    // Scoped through the store's own relation, so an id from another
    // store finds nothing rather than loading it.
    Livewire::actingAs($seller)->test(Form::class, ['certificate' => $theirs])
        ->assertNotFound();
});

function tableCert(User $seller, string $number, array $attrs = []): HalalCertificate
{
    return HalalCertificate::create(array_merge([
        'store_id' => $seller->store->id, 'number' => $number, 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Holder', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(),
    ], $attrs));
}

test('seller certificate table sorts safely, combines filters and search, and shows the count', function () {
    $seller = certSeller();
    $a = tableCert($seller, 'MY-AAA-1-100', ['valid_to' => now()->addMonths(10)]);
    $b = tableCert($seller, 'MY-BBB-2-100', ['valid_to' => now()->addMonths(2), 'issuing_body' => 'JAIS']);
    $b->forceFill(['status' => CertificateStatus::Approved])->save();
    Product::factory()->create(['store_id' => $seller->store->id, 'halal_certificate_id' => $b->id]);
    $theirs = HalalCertificate::create([
        'store_id' => Store::factory()->approved()->create()->id, 'number' => 'MY-ZZZ-9-100', 'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM', 'holder_name' => 'X', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(),
    ]);

    Livewire::actingAs($seller)->test(Certificates::class)
        ->assertSee('Showing 1 to 2 of 2')->assertDontSee($theirs->number)
        ->assertSeeInOrder(['MY-BBB-2-100', 'MY-AAA-1-100'])
        ->call('sortBy', 'number')->assertSeeInOrder(['MY-AAA-1-100', 'MY-BBB-2-100'])
        ->assertSeeHtml('aria-sort="ascending"')
        ->call('sortBy', 'number')->assertSeeInOrder(['MY-BBB-2-100', 'MY-AAA-1-100'])
        ->assertSeeHtml('aria-sort="descending"')
        ->call('sortBy', 'unknown')->assertSet('sort', 'number')
        ->set('sort', 'unknown')->set('direction', 'invalid')->assertSee('MY-AAA-1-100')
        ->set('status', 'approved')->set('body', 'JAIS')->assertSee('MY-BBB-2-100')->assertDontSee('MY-AAA-1-100')
        ->assertSee('Showing 1 to 1 of 1')
        ->set('status', '')->set('body', '')->set('search', 'AAA')->assertSee('MY-AAA-1-100')->assertDontSee('MY-BBB-2-100')
        ->set('search', '')->set('expiring', true)->assertSee('MY-BBB-2-100')->assertDontSee('MY-AAA-1-100');
});

test('seller certificate table pages', function () {
    $seller = certSeller();
    foreach (range(1, 16) as $i) {
        tableCert($seller, 'MY-PAGE-'.$i.'-100');
    }

    Livewire::actingAs($seller)->test(Certificates::class)->assertSee('Showing 1 to 15 of 16')
        ->call('gotoPage', 2)->assertSee('Showing 16 to 16 of 16')
        ->set('perPage', 50)->assertSee('Showing 1 to 16 of 16');
});

test('certificate create and edit open on their own pages', function () {
    $seller = certSeller();
    $cert = tableCert($seller, 'MY-OWN-1-100');
    $other = HalalCertificate::create([
        'store_id' => Store::factory()->approved()->create()->id, 'number' => 'MY-OTH-1-100', 'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM', 'holder_name' => 'X', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(),
    ]);

    $this->actingAs($seller)->get(route('seller.certificates.create'))->assertOk()->assertSee('Send for review');
    $this->actingAs($seller)->get(route('seller.certificates.edit', $cert))->assertOk()->assertSee('MY-OWN-1-100');
    $this->actingAs($seller)->get(route('seller.certificates.edit', $other))->assertNotFound();

    certForm(Livewire::actingAs($seller)->test(Form::class))->call('save')->assertRedirect(route('seller.certificates'));
});

// HB-G141 (HB-DEC-1124f item 7): ESMA is not on JAKIM's 21 Aug 2026 list of
// recognised foreign bodies, so a new certificate cannot name it.
test('the certificate form no longer offers or accepts ESMA', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Form::class))
        ->assertDontSeeHtml('value="ESMA"')
        ->set('issuingBody', 'ESMA')
        ->call('save')
        ->assertHasErrors(['issuingBody']);

    expect(HalalCertificate::count())->toBe(0);
});

test('an existing ESMA certificate keeps its body as a read-only label and can still be renewed', function () {
    $seller = certSeller();
    certForm(Livewire::actingAs($seller)->test(Form::class))->call('save');
    $certificate = HalalCertificate::first();
    $certificate->forceFill(['issuing_body' => 'ESMA', 'issuing_body_name' => 'Emirates Authority for Standardization and Metrology'])->save();

    Livewire::actingAs($seller)->test(Form::class, ['certificate' => $certificate])
        ->assertSee('Emirates Authority for Standardization and Metrology')
        ->assertSee('No longer accepted for new certificates.')
        ->assertDontSeeHtml('<select id="issuingBody"')
        ->set('validTo', now()->addYears(2)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect($certificate->fresh()->issuing_body)->toBe('ESMA')
        ->and($certificate->fresh()->issuing_body_name)->toBe('Emirates Authority for Standardization and Metrology');
});

test('a certificate that does not name ESMA cannot be switched to it on edit', function () {
    $seller = certSeller();
    certForm(Livewire::actingAs($seller)->test(Form::class))->call('save');
    $certificate = HalalCertificate::first();

    Livewire::actingAs($seller)->test(Form::class, ['certificate' => $certificate])
        ->set('issuingBody', 'ESMA')
        ->call('save')
        ->assertHasErrors(['issuingBody']);

    expect($certificate->fresh()->issuing_body)->not->toBe('ESMA');
});
