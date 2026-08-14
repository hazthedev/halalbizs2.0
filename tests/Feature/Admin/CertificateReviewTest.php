<?php

use App\Enums\CertificateStatus;
use App\Livewire\Admin\Catalog\Certificates;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\HalalCertificateDecision;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// Audit H-6, the review half. A seller's submission is a CLAIM; nothing badges
// a product until an admin turns it into evidence here.

function certAdmin(array $permissions = ['certificates.manage']): User
{
    test()->seed(RoleSeeder::class);

    $admin = User::factory()->create(['two_factor_method' => 'email']); // EnsureAdmin requires 2FA
    $admin->assignRole('admin');
    $admin->syncPermissions($permissions);

    return $admin->fresh();
}

function submittedCert(array $attrs = []): HalalCertificate
{
    $store = Store::factory()->approved()->create(['user_id' => User::factory()->create()->id]);

    $cert = HalalCertificate::create(array_merge([
        'store_id' => $store->id,
        'number' => 'MY-JKM-'.fake()->unique()->numberBetween(1000, 9999).'-300',
        'issuing_body' => 'JAKIM',
        'issuing_body_name' => 'JAKIM',
        'holder_name' => 'Submitting Holder',
        'valid_from' => now()->subMonth(),
        'valid_to' => now()->addYear(),
    ], $attrs));

    $cert->forceFill(['status' => CertificateStatus::Pending, 'submitted_at' => now()])->save();

    return $cert;
}

test('approving a certificate makes the badge live', function () {
    Notification::fake();
    $admin = certAdmin();
    $cert = submittedCert();
    $product = Product::factory()->create(['store_id' => $cert->store_id, 'halal_certificate_id' => $cert->id]);

    expect($product->halalVerdict())->toBe('pending');

    Livewire::actingAs($admin)->test(Certificates::class)->call('approve', $cert->id);

    expect($cert->fresh()->status)->toBe(CertificateStatus::Approved)
        ->and($cert->fresh()->reviewed_by)->toBe($admin->id)
        ->and($cert->fresh()->reviewed_at)->not->toBeNull()
        ->and($product->fresh()->halalVerdict())->toBe('verified');

    Notification::assertSentTo($cert->store->user, HalalCertificateDecision::class);
});

test('rejecting requires a reason, and the seller gets it word for word', function () {
    Notification::fake();
    $admin = certAdmin();
    $cert = submittedCert();

    Livewire::actingAs($admin)->test(Certificates::class)
        ->call('reject', $cert->id)
        ->assertHasErrors(['rejectionReason']);

    expect($cert->fresh()->status)->toBe(CertificateStatus::Pending);

    Livewire::actingAs($admin)->test(Certificates::class)
        ->set('rejectionReason', 'The scan is cut off — we cannot read the expiry date.')
        ->call('reject', $cert->id)
        ->assertHasNoErrors();

    expect($cert->fresh()->status)->toBe(CertificateStatus::Rejected)
        ->and($cert->fresh()->review_note)->toBe('The scan is cut off — we cannot read the expiry date.');
});

// The register is public, so what lands in the events table is buyer-facing.
test('an approval writes a public event and a rejection does not', function () {
    Notification::fake();
    $admin = certAdmin();

    $approved = submittedCert();
    Livewire::actingAs($admin)->test(Certificates::class)->call('approve', $approved->id);

    $rejected = submittedCert();
    Livewire::actingAs($admin)->test(Certificates::class)
        ->set('rejectionReason', 'Issuing body does not match the number prefix.')
        ->call('reject', $rejected->id);

    expect($approved->events()->count())->toBe(1)
        ->and($rejected->events()->count())->toBe(0);
});

test('both decisions are written to the audit log', function () {
    Notification::fake();
    $admin = certAdmin();
    $cert = submittedCert();

    Livewire::actingAs($admin)->test(Certificates::class)->call('approve', $cert->id);

    expect(Activity::query()->pluck('description'))->toContain('halal_certificate.approved');
});

test('the queue is admin-only and needs the certificates permission', function () {
    $this->seed(RoleSeeder::class);

    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $this->actingAs($seller)->get('/admin/catalog/certificates')->assertForbidden();

    // An admin without this specific grant must not reach it either — the
    // admin role itself carries zero permissions by design.
    $this->actingAs(certAdmin(['orders.manage']))->get('/admin/catalog/certificates')->assertForbidden();

    $this->actingAs(certAdmin())->get('/admin/catalog/certificates')->assertOk();
});

test('the uploaded scan is not readable without the permission', function () {
    $cert = submittedCert();
    // A real temp file, not a faked upload: addMedia MOVES what it is given,
    // and UploadedFile::fake()'s temp path is cleaned up underneath it.
    $path = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
    file_put_contents($path, '%PDF-1.4 test');

    $cert->addMedia($path)->usingFileName('scan.pdf')->toMediaCollection('document');

    $url = "/admin/catalog/certificates/{$cert->id}/document";

    $this->actingAs(certAdmin(['orders.manage']))->get($url)->assertForbidden();
    $this->actingAs(certAdmin())->get($url)->assertOk();
});

test('the queue shows the oldest submission first', function () {
    $admin = certAdmin();
    $older = submittedCert();
    $older->forceFill(['submitted_at' => now()->subWeek()])->save();
    $newer = submittedCert();

    $ids = Livewire::actingAs($admin)->test(Certificates::class)
        ->viewData('certificates')->pluck('id')->all();

    expect($ids)->toBe([$older->id, $newer->id]);
});
