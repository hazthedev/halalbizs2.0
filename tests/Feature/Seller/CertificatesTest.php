<?php

use App\Enums\CertificateStatus;
use App\Livewire\Seller\Certificates;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))
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

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))->call('save');

    // Spatie's default disk is the WEB-SERVED public one — no config/media-library.php
    // is published and MEDIA_DISK is unset — so this is the assertion that
    // stops certificate scans landing on a guessable URL.
    expect(HalalCertificate::first()->getFirstMedia('document')->disk)->toBe('local');
});

test('a certificate covers only the products the seller ticks', function () {
    $seller = certSeller();
    $mine = Product::factory()->create(['store_id' => $seller->store->id]);
    $alsoMine = Product::factory()->create(['store_id' => $seller->store->id]);

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))
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

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))
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

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))->call('save');

    $certificate = HalalCertificate::first();
    $certificate->forceFill(['status' => CertificateStatus::Approved])->save();

    Livewire::actingAs($seller)->test(Certificates::class)
        ->call('edit', $certificate->id)
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

    certForm(Livewire::actingAs(certSeller())->test(Certificates::class)->call('startCreate'))
        ->call('save')
        ->assertHasErrors(['number']);
});

test('a term longer than five years is rejected as a typo', function () {
    $seller = certSeller();

    certForm(Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate'))
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

    $component = Livewire::actingAs($seller)->test(Certificates::class)
        ->assertDontSee('MY-JKM-7777-777');

    // Scoped through the store's own relation, so a posted id from another
    // store finds nothing rather than loading it.
    expect(fn () => $component->call('edit', $theirs->id))
        ->toThrow(ModelNotFoundException::class);
});
