<?php

use App\Enums\CertificateStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Catalog\Certificates as AdminCertificates;
use App\Livewire\Seller\Certificates;
use App\Livewire\Seller\Dashboard;
use App\Livewire\Seller\Notifications;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Notifications\WhipCertificateExpiry;
use App\Services\ProductWriteService;
use App\Services\Whip\WhipDirectory;
use App\Settings\ModerationSettings;
use App\Support\DisplayTime;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// WHIP is read through a second connection. Tests point it at in-memory SQLite
// and create only the columns the directory reads.

beforeEach(function () {
    $s = Schema::connection('whip');
    $s->create('halal_applications', function ($t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->default(1);
        $t->string('application_no')->default('HALAL-TEST');
        $t->dateTime('submitted_at')->nullable();
        $t->unsignedBigInteger('certification_body_id')->default(1);
        $t->string('status');
    });
    $s->create('halal_application_certificates', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('certificate_no')->nullable();
        $t->date('valid_from')->nullable();
        $t->date('valid_to')->nullable();
        $t->text('services_text')->nullable();
    });
    $s->create('halal_application_company_profiles', function ($t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->default(1);
        $t->unsignedBigInteger('application_id');
        $t->string('company_name')->nullable();
        $t->string('registration_number')->nullable();
    });
    $s->create('halal_application_products', function ($t) {
        $t->id();
        $t->unsignedBigInteger('application_id');
        $t->string('product_name');
        $t->string('brand_name')->nullable();
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
    foreach ($o['products'] ?? [['product_name' => 'Kicap', 'barcode' => '955000']] as $p) {
        $w->table('halal_application_products')->insert($p + ['application_id' => $app]);
    }

    return $app;
}

function whipSeller(?string $reg = '202001000123', bool $confirmed = true): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create([
        'user_id' => $user->id, 'registration_no' => $reg,
        'registration_verified_at' => $confirmed && $reg ? now() : null,
    ]);

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

test('a seller finds and links only certificates for their verified store SSM without retyping certificate fields', function () {
    whipCert();
    whipCert(['no' => 'PRIVATE-OTHER', 'reg' => '999999-X']);
    $seller = whipSeller();

    Livewire::actingAs($seller)->test(Certificates::class)
        ->call('findWhipCertificates')->assertSee('WH-100')->assertDontSee('PRIVATE-OTHER')
        ->call('linkWhipCertificate', 'WH-100')->assertHasNoErrors();

    expect($seller->store->halalCertificates()->first())
        ->number->toBe('WH-100')->source->toBe('whip')->status->toBe(CertificateStatus::Approved);
});

test('linking a WHIP certificate preserves its full scope beyond the manual form limit', function () {
    whipCert();
    $scope = str_repeat('Sauces and condiments. ', 30);
    DB::connection('whip')->table('halal_application_certificates')->update(['services_text' => $scope]);
    Livewire::actingAs(whipSeller())->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')->assertHasNoErrors();
    expect(whipRow()->scope_note)->toBe($scope);
});

test('sync keeps an approved WHIP certificate approved when its company name becomes blank', function () {
    whipCert();
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    DB::connection('whip')->table('halal_application_company_profiles')->update(['company_name' => null]);
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect(whipRow()->status)->toBe(CertificateStatus::Approved)
        ->and(whipRow()->whip_answer)->toBe('verified')
        ->and(whipRow()->holder_name)->toBe('WHIP Foods Sdn Bhd');
});

test('WHIP discovery requires a verified SSM and direct link requests cannot borrow another company certificate', function () {
    whipCert(['reg' => '999999-X']);
    $unverified = whipSeller(confirmed: false);
    Livewire::actingAs($unverified)->test(Certificates::class)
        ->call('findWhipCertificates')->assertHasErrors('whip')->assertDontSee('WH-100');
    Livewire::actingAs($unverified)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')->assertForbidden();

    Livewire::actingAs(whipSeller())->test(Certificates::class)
        ->set('whipCertificates', [['number' => 'WH-100', 'scope' => null, 'status' => 'active', 'expiry' => null, 'can_link' => true]])
        ->call('linkWhipCertificate', 'WH-100')->assertHasErrors('whip');
    expect(HalalCertificate::count())->toBe(0);
});

test('WHIP discovery shows scope and expired status but a changed certificate cannot be linked', function () {
    $app = whipCert();
    DB::connection('whip')->table('halal_application_certificates')->update(['services_text' => 'Sauces and condiments']);
    $seller = whipSeller();
    $page = Livewire::actingAs($seller)->test(Certificates::class)->call('findWhipCertificates')
        ->assertSee('Sauces and condiments');
    DB::connection('whip')->table('halal_applications')->where('id', $app)->update(['status' => 'revoked']);
    $page->call('linkWhipCertificate', 'WH-100')->assertHasErrors('whip');
    expect(HalalCertificate::count())->toBe(0);
});

test('a bound WHIP certificate imports products as reviewable drafts once with brand and barcode', function () {
    Brand::create(['name' => 'SEDAP', 'is_active' => true]);
    whipCert(['products' => [['product_name' => 'Kicap', 'brand_name' => 'Sedap', 'barcode' => '955000']]]);
    $seller = whipSeller();
    $category = Category::factory()->create(['is_active' => true]);
    $page = Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $page->set('importCategoryId', $category->id)->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    $product = $seller->store->products()->with('variants', 'brand')->firstOrFail();
    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->name)->toBe('Kicap')->and($product->brand->name)->toBe('SEDAP')
        ->and($product->variants->sole()->sku)->toBe('955000')
        ->and($product->halal_certificate_id)->toBe(whipRow()->id);
    $page->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    expect($seller->store->products()->count())->toBe(1);
    $this->actingAs(User::factory()->create())->get('/p/'.$product->slug)->assertNotFound();
});

test('an unknown WHIP brand creates no shared catalogue brand and leaves the draft brand unset', function () {
    whipCert(['products' => [['product_name' => 'Kicap', 'brand_name' => 'Unknown WHIP Brand', 'barcode' => '955000']]]);
    $seller = whipSeller();
    $category = Category::factory()->create(['is_active' => true]);
    $before = Brand::count();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')
        ->set('importCategoryId', $category->id)->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    expect(Brand::count())->toBe($before)
        ->and($seller->store->products()->firstOrFail()->brand_id)->toBeNull();
});

test('an inactive catalogue brand is not assigned by a WHIP import', function () {
    Brand::create(['name' => 'Sedap', 'is_active' => false]);
    whipCert(['products' => [['product_name' => 'Kicap', 'brand_name' => 'Sedap', 'barcode' => null]]]);
    $seller = whipSeller();
    $category = Category::factory()->create(['is_active' => true]);
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')
        ->set('importCategoryId', $category->id)->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    expect(Brand::count())->toBe(1)
        ->and($seller->store->products()->firstOrFail()->brand_id)->toBeNull();
});

test('an imported WHIP draft cannot be published before its price is reviewed', function () {
    whipCert();
    $seller = whipSeller();
    $category = Category::factory()->create(['is_active' => true]);
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')
        ->set('importCategoryId', $category->id)->call('importWhipProducts', whipRow()->id);
    $product = $seller->store->products()->firstOrFail();
    $product->addMedia(UploadedFile::fake()->image('sauce.jpg'))->toMediaCollection('images');
    app(ModerationSettings::class)->require_product_approval = false;
    expect(fn () => app(ProductWriteService::class)->publish($product))
        ->toThrow(ValidationException::class);
    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

test('product import rejects another store certificate and an unconfirmed WHIP renewal', function () {
    $app = whipCert();
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $certificate = whipRow();
    $category = Category::factory()->create(['is_active' => true]);
    expect(fn () => Livewire::actingAs(whipSeller('999999-X'))->test(Certificates::class)
        ->set('importCategoryId', $category->id)->call('importWhipProducts', $certificate->id))
        ->toThrow(ModelNotFoundException::class);
    DB::connection('whip')->table('halal_applications')->where('id', $app)->update(['status' => 'revoked']);
    Livewire::actingAs($seller)->test(Certificates::class)
        ->set('importCategoryId', $category->id)->call('importWhipProducts', $certificate->id)->assertHasErrors('whip');
    expect($seller->store->products()->count())->toBe(0);
});

test('imported drafts stay private until seller publication and lose certification on the existing recheck', function () {
    whipCert();
    $seller = whipSeller();
    $category = Category::factory()->create(['is_active' => true]);
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')
        ->set('importCategoryId', $category->id)->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    $product = $seller->store->products()->firstOrFail();
    $this->actingAs(User::factory()->create())->get('/p/'.$product->slug)->assertNotFound();
    $product->addMedia(UploadedFile::fake()->image('sauce.jpg'))->toMediaCollection('images');
    app(ProductWriteService::class)->update($product, ['price_sen' => 250, 'stock' => 10]);
    app(ModerationSettings::class)->require_product_approval = false;
    app(ProductWriteService::class)->publish($product);
    $this->get('/p/'.$product->slug)->assertOk()->assertSee('Halal certificate verified');
    DB::connection('whip')->table('halal_applications')->update(['status' => 'revoked']);
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect($product->fresh()->halalVerdict())->not->toBe('verified');
});

test('WHIP expiry reminders send seller and email notices once at 60 30 and 7 days for each expiry term', function () {
    Notification::fake();
    config(['services.whip.renew_url' => 'https://whip.example.test/company_login.php']);
    $this->travelTo(now()->startOfDay());
    $expiry = DisplayTime::today()->addDays(60)->toDateString();
    whipCert(['to' => $expiry]);
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $certificate = whipRow();

    foreach ([60, 30, 7] as $days) {
        $this->travelTo(DisplayTime::today()->parse($expiry)->subDays($days)->startOfDay());
        $certificate->forceFill(['whip_synced_at' => now()])->save();
        $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
        $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
        Notification::assertSentTo($seller, WhipCertificateExpiry::class,
            fn ($notice) => $notice->days === $days && $notice->via($seller) === ['database', 'mail']
                && $notice->toMail($seller)->actionUrl === 'https://whip.example.test/company_login.php');
    }
    Notification::assertSentToTimes($seller, WhipCertificateExpiry::class, 3);
    $certificate->forceFill(['valid_to' => DisplayTime::today()->addDays(60), 'whip_synced_at' => now()])->save();
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    Notification::assertSentToTimes($seller, WhipCertificateExpiry::class, 4);
});

test('WHIP reminders skip stale or rejected checks and do not receive the manual certificate nudge', function () {
    Notification::fake();
    whipCert(['to' => DisplayTime::today()->addDays(60)->toDateString()]);
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $certificate = whipRow();
    $certificate->forceFill(['whip_synced_at' => now()->subHours(25)])->save();
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    $certificate->forceFill(['whip_synced_at' => now(), 'whip_answer' => 'mismatch'])->save();
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    $this->artisan('certificates:watch-expiry')->assertSuccessful();
    Notification::assertNothingSent();
});

test('WHIP reminder is visible to its seller and opens the configured renewal page', function () {
    whipCert();
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    config(['services.whip.renew_url' => 'https://whip.example.test/company_login.php']);
    $notice = new WhipCertificateExpiry(whipRow(), 60);
    $notification = $seller->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => $notice::class, 'data' => $notice->toArray($seller),
    ]);
    Livewire::actingAs($seller)->test(Notifications::class)
        ->assertSee('Certificate WH-100 expires')->call('open', $notification->id)
        ->assertRedirect('https://whip.example.test/company_login.php');
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a recovered scheduler catches the current WHIP reminder window without sending obsolete milestones', function () {
    Notification::fake();
    whipCert(['to' => DisplayTime::today()->addDays(58)->toDateString()]);
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    Notification::assertSentToTimes($seller, WhipCertificateExpiry::class, 1);
    Notification::assertSentTo($seller, WhipCertificateExpiry::class, fn ($notice) => $notice->days === 58);
});

test('a failed WHIP reminder enqueue leaves its milestone available for retry', function () {
    whipCert(['to' => DisplayTime::today()->addDays(60)->toDateString()]);
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    config(['queue.default' => 'database']);
    Event::listen(JobQueueing::class, fn () => throw new RuntimeException('Queue unavailable'));
    expect(fn () => Artisan::call('certificates:remind-whip-expiry'))->toThrow(RuntimeException::class, 'Queue unavailable');
    expect(whipRow()->whip_reminders)->toBeNull();
    Event::forget(JobQueueing::class);
    $this->artisan('certificates:remind-whip-expiry')->assertSuccessful();
    expect(whipRow()->whip_reminders['sent'])->toBe([60]);
    $this->assertDatabaseCount('jobs', 2);
});

test('seller dashboard tracks their verified company WHIP application before any certificate exists', function () {
    $db = DB::connection('whip');
    $application = $db->table('halal_applications')->insertGetId(['user_id' => 101, 'application_no' => 'HALAL-200', 'status' => 'Submitted']);
    $db->table('halal_application_company_profiles')->insert(['application_id' => $application, 'user_id' => 101, 'registration_number' => '202001-000123']);
    $other = $db->table('halal_applications')->insertGetId(['user_id' => 202, 'application_no' => 'PRIVATE-OTHER', 'status' => 'Draft']);
    $db->table('halal_application_company_profiles')->insert(['application_id' => $other, 'user_id' => 202, 'registration_number' => '999999-X']);
    $badOwner = $db->table('halal_applications')->insertGetId(['user_id' => 303, 'application_no' => 'WRONG-OWNER', 'status' => 'Draft']);
    $db->table('halal_application_company_profiles')->insert(['application_id' => $badOwner, 'user_id' => 404, 'registration_number' => '202001000123']);

    Livewire::actingAs(whipSeller())->test(Dashboard::class)
        ->assertSee('HALAL-200')->assertSee('Submitted')->assertDontSee('PRIVATE-OTHER')->assertDontSee('WRONG-OWNER')
        ->tap(function ($page) use ($db, $application) {
            $db->table('halal_applications')->where('id', $application)->update(['status' => 'certified']);
            $page->call('refreshWhipApplications')->assertSee('Certified');
        });
    Livewire::actingAs(whipSeller(confirmed: false))->test(Dashboard::class)
        ->assertDontSee('HALAL-200');
});

test('WHIP application tracker remains usable when the directory is unavailable', function () {
    Schema::connection('whip')->drop('halal_applications');
    Livewire::actingAs(whipSeller())->test(Dashboard::class)
        ->assertSee('WHIP is unavailable')->assertSee('Manage my certificates');
});

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

    expect(app(WhipDirectory::class)->lookup('  '))->toBeNull();
});

test('number match ignores case and spaces around it, and a not-certified application is not active', function () {
    whipCert(['no' => ' wh-100 ', 'status' => 'Under Review']);

    expect(app(WhipDirectory::class)->lookup('WH-100')->status)->toBe('not_certified');
});

test('two active records with one number are ambiguous, not verified', function () {
    whipCert();
    whipCert();
    whipSubmit(whipSeller());

    expect(whipRow())->status->toBe(CertificateStatus::Pending)->whip_answer->toBe('ambiguous');
});

test('sync reads existing and followed WHIP certificates without opening a local transaction', function () {
    whipCert();
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $application = whipCert(['no' => 'WH-200']);
    $seller->store->forceFill(['whip_application_id' => $application])->save();
    // RefreshDatabase owns the outer test transaction; sync must not add one during remote reads.
    $outerLevel = DB::transactionLevel();
    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels) {
        if ($query->connectionName === 'whip') {
            $levels[] = DB::transactionLevel();
        }
    });

    $this->artisan('certificates:sync-whip')->assertSuccessful();

    expect($levels)->not->toBeEmpty()
        ->and(array_values(array_unique($levels)))->toBe([$outerLevel]);
    expect(whipRow('WH-200')->store_id)->toBe($seller->store->id);
});

test('sync rechecks the followed store after reading WHIP before creating its certificate', function (array $change) {
    $application = whipCert();
    $seller = whipSeller();
    $seller->store->forceFill(['whip_application_id' => $application])->save();
    $changed = false;
    DB::listen(function (QueryExecuted $query) use ($seller, $change, &$changed) {
        if ($query->connectionName === 'whip' && ! $changed) {
            $changed = true;
            $seller->store->forceFill($change)->save();
        }
    });

    $this->artisan('certificates:sync-whip')->assertSuccessful();

    expect($changed)->toBeTrue()->and(HalalCertificate::count())->toBe(0);
})->with([
    'verification removed' => [['registration_verified_at' => null]],
    'company changed' => [['registration_no' => '999999-X']],
    'application unfollowed' => [['whip_application_id' => null]],
]);

test('the sync moves a certificate WHIP now holds as expired to expired and delists its products', function () {
    $app = whipCert();
    whipSubmit(whipSeller());
    $c = whipRow();
    $product = Product::factory()->create(['store_id' => $c->store_id, 'name' => 'Kicap', 'status' => ProductStatus::Live, 'halal_certificate_id' => $c->id]);

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

test('a registration number the seller typed is not trusted: WHIP match goes to review, admin approval confirms it, the next one is instant', function () {
    whipCert();
    whipCert(['no' => 'WH-200']);
    $seller = whipSeller(confirmed: false);

    whipSubmit($seller);
    expect(whipRow())->status->toBe(CertificateStatus::Pending)->source->toBe('manual')->whip_answer->toBe('verified');

    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->syncPermissions(['certificates.manage']);
    Livewire::actingAs($admin->fresh())->test(AdminCertificates::class)->call('approve', whipRow()->id);
    expect(whipRow())->status->toBe(CertificateStatus::Approved)->source->toBe('whip')
        ->and($seller->store->fresh()->registration_verified_at)->not->toBeNull();

    whipSubmit($seller->fresh(), 'WH-200');
    expect(whipRow('WH-200'))->status->toBe(CertificateStatus::Approved)->source->toBe('whip');
});

// ── Badge only on products WHIP certified ────────────────────────────────

function whipProduct(HalalCertificate|Store $of, string $name, ?string $sku = null): Product
{
    $p = Product::factory()->create(['store_id' => $of instanceof Store ? $of->id : $of->store_id, 'name' => $name, 'status' => ProductStatus::Live]);
    ProductVariant::factory()->create(['product_id' => $p->id, 'sku' => $sku ?? 'X-'.$p->id]);

    return $p;
}

/** WHIP lists Kicap (barcode 955000) and "Brand Sos Cili"; the seller ticks 3, one not on it. */
function whipTicked(): array
{
    whipCert(['products' => [
        ['product_name' => 'Kicap', 'barcode' => '955000'],
        ['product_name' => 'Sos Cili', 'brand_name' => null, 'barcode' => null],
    ]]);
    $seller = whipSeller();
    $byBarcode = whipProduct($seller->store, 'Anything', ' 955000 ');
    $byName = whipProduct($seller->store, 'SOS  cili!');
    $stranger = whipProduct($seller->store, 'Ayam Goreng');

    Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate')
        ->set('number', 'WH-100')->set('issuingBody', 'JAKIM')->set('holderName', 'Typed Name')
        ->set('validFrom', now()->subMonth()->toDateString())->set('validTo', now()->addYear()->toDateString())
        ->set('document', UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'))
        ->set('covered', [$byBarcode->id, $byName->id, $stranger->id])
        ->call('save')->assertHasNoErrors();

    return [$seller, $byBarcode, $byName, $stranger];
}

function whipAdmin(): User
{
    test()->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->syncPermissions(['certificates.manage']);

    return $admin->fresh();
}

test('a ticked product that is not on WHIP\'s certificate gets no badge', function () {
    [, $byBarcode, $byName, $stranger] = whipTicked();

    expect($byBarcode->fresh()->halalVerdict())->toBe('verified')   // barcode = sku, trimmed
        ->and($byName->fresh()->halalVerdict())->toBe('verified')   // name, case/punctuation ignored
        ->and($stranger->fresh()->halal_certificate_id)->toBeNull()
        ->and(whipRow()->whip_unmatched_product_ids)->toBe([$stranger->id]);
});

test('admin Allow binds an unmatched product and the next sync keeps it', function () {
    [, , , $stranger] = whipTicked();
    $c = whipRow();

    Livewire::actingAs(whipAdmin())->test(AdminCertificates::class)->call('allowProduct', $c->id, $stranger->id);

    expect($stranger->fresh()->halalVerdict())->toBe('verified')
        ->and($c->fresh()->whip_unmatched_product_ids)->toBe([])
        ->and($c->events()->count())->toBe(2);

    $this->artisan('certificates:sync-whip')->assertSuccessful();

    expect($stranger->fresh()->halal_certificate_id)->toBe($c->id);
});

test('the sync unbinds a product WHIP dropped', function () {
    [, $byBarcode, $byName] = whipTicked();
    DB::connection('whip')->table('halal_application_products')->where('product_name', 'Kicap')->delete();

    $this->artisan('certificates:sync-whip')->assertSuccessful();

    expect($byBarcode->fresh()->halal_certificate_id)->toBeNull()
        ->and($byName->fresh()->halal_certificate_id)->toBe(whipRow()->id)
        ->and(whipRow()->whip_unmatched_product_ids)->toContain($byBarcode->id);
});

test('a manual certificate binds every ticked product, unchanged', function () {
    $seller = whipSeller();
    $p = whipProduct($seller->store, 'Ayam Goreng');
    Livewire::actingAs($seller)->test(Certificates::class)->call('startCreate')
        ->set('number', 'MAN-1')->set('issuingBody', 'JAKIM')->set('holderName', 'X')
        ->set('validFrom', now()->subMonth()->toDateString())->set('validTo', now()->addYear()->toDateString())
        ->set('document', UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'))
        ->set('covered', [$p->id])->call('save')->assertHasNoErrors();

    expect($p->fresh()->halal_certificate_id)->toBe(whipRow('MAN-1')->id)
        ->and(whipRow('MAN-1')->whip_unmatched_product_ids)->toBeNull();
});

test('a WHIP certificate missing its company name stays unlinked for review', function () {
    whipCert();
    DB::connection('whip')->table('halal_application_company_profiles')->update(['company_name' => null]);
    Livewire::actingAs(whipSeller())->test(Certificates::class)
        ->call('linkWhipCertificate', 'WH-100')->assertHasErrors('whip');
    expect(HalalCertificate::count())->toBe(0);
});

test('linking WHIP again keeps products already waiting for admin review', function () {
    [$seller, , , $parked] = whipTicked();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100')->assertHasNoErrors();
    expect(whipRow()->whip_unmatched_product_ids)->toBe([$parked->id]);
});

test('importing WHIP products keeps products already waiting for admin review', function () {
    [$seller, , , $parked] = whipTicked();
    $category = Category::factory()->create(['is_active' => true]);
    Livewire::actingAs($seller)->test(Certificates::class)->set('importCategoryId', $category->id)
        ->call('importWhipProducts', whipRow()->id)->assertHasNoErrors();
    expect(whipRow()->whip_unmatched_product_ids)->toBe([$parked->id]);
});

test('following a pending WHIP application lets the existing recheck bind its issued certificate and matching products', function () {
    $application = whipCert(['status' => 'Submitted']);
    DB::connection('whip')->table('halal_application_certificates')->delete();
    $seller = whipSeller();
    $product = whipProduct($seller->store, 'Kicap', '955000');
    $other = whipProduct($seller->store, 'Uncertified product');
    Livewire::actingAs($seller)->test(Dashboard::class)
        ->call('followWhipApplication', $application)->assertHasNoErrors();
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect($product->fresh()->halal_certificate_id)->toBeNull();

    DB::connection('whip')->table('halal_applications')->where('id', $application)->update(['status' => 'certified']);
    DB::connection('whip')->table('halal_application_certificates')->insert([
        'application_id' => $application, 'certificate_no' => 'WH-ISSUED',
        'valid_from' => now()->subDay()->toDateString(), 'valid_to' => now()->addYear()->toDateString(),
    ]);
    DB::connection('whip')->table('halal_application_company_profiles')->where('application_id', $application)->update(['company_name' => null]);
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect($product->fresh()->halal_certificate_id)->toBeNull();
    DB::connection('whip')->table('halal_application_company_profiles')->where('application_id', $application)->update(['company_name' => 'WHIP Foods Sdn Bhd']);
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect($product->fresh()->halalVerdict())->toBe('verified')
        ->and($product->fresh()->halalCertificate->number)->toBe('WH-ISSUED')
        ->and($other->fresh()->halal_certificate_id)->toBeNull();
    $this->artisan('certificates:sync-whip')->assertSuccessful();
    expect($seller->store->halalCertificates()->count())->toBe(1);
});

test('an application cannot be followed using a forged card or an unverified SSM', function () {
    $application = whipCert();
    foreach ([whipSeller('999999-X'), whipSeller(confirmed: false)] as $seller) {
        Livewire::actingAs($seller)->test(Dashboard::class)
            ->set('whipApplications', [['id' => $application, 'number' => 'WH-100', 'status' => 'certified']])
            ->call('followWhipApplication', $application)->assertForbidden();
        expect($seller->store->fresh()->whip_application_id)->toBeNull();
    }
});

test('a followed application lookup outage leaves existing certificate checks unchanged', function () {
    whipCert();
    $seller = whipSeller();
    Livewire::actingAs($seller)->test(Certificates::class)->call('linkWhipCertificate', 'WH-100');
    $seller->store->forceFill(['whip_application_id' => whipRow()->whip_application_id])->save();
    DB::connection('whip')->table('halal_applications')->update(['status' => 'revoked']);
    $directory = Mockery::mock(WhipDirectory::class)->makePartial();
    $directory->shouldReceive('forCompany')->andThrow(new RuntimeException('WHIP unavailable'));
    app()->instance(WhipDirectory::class, $directory);
    $this->artisan('certificates:sync-whip')->assertFailed();
    expect(whipRow()->status)->toBe(CertificateStatus::Approved)->and(whipRow()->whip_answer)->toBe('verified');
});

test('following an application reports a directory outage without saving a choice', function () {
    $seller = whipSeller();
    Schema::connection('whip')->drop('halal_applications');
    Livewire::actingAs($seller)->test(Dashboard::class)
        ->call('followWhipApplication', 1)->assertHasErrors('whip');
    expect($seller->store->fresh()->whip_application_id)->toBeNull();
});
