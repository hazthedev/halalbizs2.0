<?php

use App\Enums\CertificateStatus;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Livewire\Admin\Orders\Payments;
use App\Livewire\Admin\System\Staff;
use App\Livewire\Seller\Certificates as SellerCertificates;
use App\Livewire\Seller\Products\Form as ProductForm;
use App\Livewire\Storefront\Auth\Login;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Models\Address;
use App\Models\Category;
use App\Models\HalalCertificate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Ipay88Service;
use App\Services\SubOrderStatusService;
use App\Services\Whip\WhipDirectory;
use App\Settings\Ipay88Settings;
use App\Settings\ModerationSettings;
use App\Settings\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
    Queue::fake([PerformConversionsJob::class]);
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'DUMMY';
    $settings->merchant_key = 'dummy-local-only';
    $settings->save();
});

function auditLeadOrder(): Order
{
    $buyer = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create();
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 5]);
    app(CartService::class)->addItem($buyer, $variant->fresh(), 2);

    return app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
}

function auditLeadCallback(Payment $payment, string $status): array
{
    return [
        'MerchantCode' => 'DUMMY', 'PaymentId' => '2', 'RefNo' => $payment->ref_no,
        'Amount' => Ipay88Service::formatAmount($payment->amount_sen), 'Currency' => 'MYR',
        'TransId' => 'DUMMY-TRANSACTION', 'Status' => $status,
        'Signature' => app(Ipay88Service::class)->responseSignature('2', $payment->ref_no, $payment->amount_sen, 'MYR', $status),
    ];
}

test('payment-callback-failure-overwrites-success: an overlapping failure cannot undo settlement or repeat OrderPaid', function () {
    Http::fake(['*' => Http::response('00')]);
    Event::fake([OrderPaid::class]);
    $this->freezeTime();
    $order = auditLeadOrder();
    $payment = $order->payment;
    $success = auditLeadCallback($payment, '1');
    $failure = auditLeadCallback($payment, '0');
    $gateway = app(Ipay88Service::class);
    $paidAt = null;

    // Pause the failure callback at the gateway boundary after its payment read.
    // A real confirmation job commits while that callback still holds stale state.
    $this->partialMock(Ipay88Service::class, function ($mock) use ($gateway, $payment, $success, &$paidAt) {
        $mock->shouldReceive('verifyResponseSignature')->once()->andReturnUsing(function ($payload) use ($gateway, $payment, $success, &$paidAt) {
            $valid = $gateway->verifyResponseSignature($payload);
            (new ConfirmIpay88PaymentJob($payment->fresh(), $success))->handle($gateway, app(SubOrderStatusService::class));
            $paidAt = $payment->fresh()->paid_at;
            $this->travel(1)->minutes();

            return $valid;
        });
    });

    $this->post(route('payments.ipay88.backend'), $failure)->assertOk()->assertSee('RECEIVEOK');
    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Success)
        ->and($payment->fresh()->response_payload)->toBe($success)
        ->and($payment->fresh()->requery_result)->toBe('00');

    $this->app->instance(Ipay88Service::class, $gateway);
    $this->post(route('payments.ipay88.backend'), $success)->assertOk();

    expect($payment->fresh()->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($order->fresh()->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->subOrders->first()->fresh()->status)->toBe(SubOrderStatus::Confirmed)
        ->and($order->subOrders->first()->statusHistories()->count())->toBe(2);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
    Http::assertSentCount(1);
});

test('payment-expiry-late-success: verified late funds for cancelled fulfilment create a refund obligation', function () {
    Notification::fake();
    Event::fake([OrderPaid::class]);
    config(['services.ipay88.refund_url' => 'https://dummy.invalid/refund']);
    $order = auditLeadOrder();
    $payment = $order->payment;
    $subOrder = $order->subOrders->first();
    $variant = $subOrder->items->first()->variant;
    $order->update(['expires_at' => now()->subMinute()]);

    Http::fakeSequence()->push('Record not found')->push('00');
    $this->artisan('orders:expire-unpaid')->assertSuccessful();
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Expired)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($variant->fresh()->stock)->toBe(5);

    // Conditional provider sequence, not evidence that iPay88 delivers it.
    $this->post(route('payments.ipay88.backend'), auditLeadCallback($payment, '1'))->assertOk();
    expect($payment->fresh()->status)->toBe(GatewayPaymentStatus::Success)
        ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($variant->fresh()->stock)->toBe(5)
        ->and($subOrder->fresh()->refunded_sen)->toBe($subOrder->total_sen)
        ->and($payment->fresh()->refunded_sen)->toBe($order->grand_total_sen)
        ->and($payment->fresh()->gateway_refund_ok)->toBeFalse();

    $this->post(route('payments.ipay88.backend'), auditLeadCallback($payment, '1'))->assertOk();
    expect($payment->fresh()->refunded_sen)->toBe($order->grand_total_sen)
        ->and($subOrder->fresh()->refunded_sen)->toBe($subOrder->total_sen);
    Event::assertNotDispatched(OrderPaid::class);
    Http::assertSentCount(2); // expiry requery + late-payment requery; no refund call

    $admin = makeAdmin(User::factory()->create());
    Livewire::actingAs($admin)->test(Payments::class)
        ->set('needsPortalRefund', true)->assertSee($payment->ref_no)
        ->assertSee(__('Needs portal refund'));
});

test('seller-product-form-banned-status: seller saves cannot clear a moderator ban', function (string $action, bool $approval, bool $stale = false) {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);
    $category = Category::factory()->create(['requires_halal_certificate' => false]);
    $product = Product::factory()->create([
        'store_id' => $store->id, 'category_id' => $category->id,
        'status' => $stale ? ProductStatus::Draft : ProductStatus::Banned,
    ]);
    $settings = app(ModerationSettings::class);
    $settings->require_product_approval = $approval;
    $settings->save();
    Storage::fake('public');

    $page = Livewire::actingAs($seller)
        ->test(ProductForm::class, ['product' => $product])
        ->set('price', '100.00')->set('salePrice', '')->set('stock', '5');
    if ($action === 'publish') {
        $page->set('newImages', [UploadedFile::fake()->image('product.jpg')]);
    }
    if ($stale) {
        $product->update(['status' => ProductStatus::Banned]);
    }
    $page->call($action)->assertHasErrors(['status'])
        ->assertSee(__('This product is banned. Ask an admin to lift the ban.'));

    expect($product->fresh()->status)->toBe(ProductStatus::Banned);
})->with([
    'draft with approval' => ['saveDraft', true],
    'draft without approval' => ['saveDraft', false],
    'review submission' => ['publish', true],
    'direct publication' => ['publish', false],
    'stale draft' => ['saveDraft', false, true],
    'stale publication' => ['publish', false, true],
]);

test('seller-product-form-publish-halal-category: publication checks the submitted gated category', function (bool $existing) {
    config(['halal.gate_enforced_from' => now()->subDay()->toDateString()]);
    $settings = app(ModerationSettings::class);
    $settings->require_product_approval = false;
    $settings->save();
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);
    $gated = Category::factory()->create(['requires_halal_certificate' => true]);
    $ungated = Category::factory()->create(['requires_halal_certificate' => false]);
    $product = $existing ? Product::factory()->create([
        'store_id' => $store->id, 'category_id' => $ungated->id,
        'status' => ProductStatus::Draft,
    ]) : null;
    Storage::fake('public');

    Livewire::actingAs($seller)
        ->test(ProductForm::class, ['product' => $product])
        ->set('name.en', 'Dummy food')
        ->set('categoryTop', $gated->id)
        ->set('price', '100.00')->set('salePrice', '')->set('stock', '5')
        ->set('newImages', [UploadedFile::fake()->image('product.jpg')])
        ->call('publish')->assertHasErrors(['halalGate']);

    expect(Product::where('store_id', $store->id)
        ->where('category_id', $gated->id)
        ->where('status', ProductStatus::Live)->exists())->toBeFalse();
})->with(['new listing' => false, 'changed category' => true]);

test('password-reset-existing-web-sessions: recovery revokes both existing browser sessions', function (string $driver) {
    $files = sys_get_temp_dir().'/hb-rules-sessions-'.Str::random(10);
    if ($driver === 'file') {
        mkdir($files);
    }
    config(['session.driver' => $driver, 'session.files' => $files]);
    $settings = app(SecuritySettings::class);
    $settings->breached_password_check = false;
    $settings->save();
    $user = User::factory()->create();
    $sessionIds = [Str::random(40), Str::random(40)];
    $loginKey = Auth::guard('web')->getName();
    foreach ($sessionIds as $id) {
        $payload = json_encode([
            $loginKey => $user->id, 'password_hash_web' => $user->password,
            '_token' => Str::random(40),
        ]);
        if ($driver === 'database') {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'last_activity' => time(),
                'payload' => base64_encode($payload),
            ]);
        } else {
            file_put_contents($files.'/'.$id, $payload);
        }
    }
    $newBrowser = function () {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Session::clearResolvedInstance('session');
    };
    foreach ($sessionIds as $id) {
        $newBrowser();
        $this->withCookie(config('session.cookie'), $id)->get(route('account.orders'))->assertOk();
    }

    $newBrowser();
    $token = Password::createToken($user);
    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'dummy-recovery-password')
        ->set('password_confirmation', 'dummy-recovery-password')
        ->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('login'));
    expect(Hash::check('dummy-recovery-password', $user->fresh()->password))->toBeTrue();

    $statuses = [];
    foreach ($sessionIds as $id) {
        $newBrowser();
        $statuses[] = $this->withCookie(config('session.cookie'), $id)->get(route('account.orders'))->status();
    }
    if ($driver === 'file') {
        foreach (glob($files.'/*') as $file) {
            unlink($file);
        }
        rmdir($files);
    }
    expect($statuses)->toBe([302, 302]);
})->with(['database', 'file']);

test('staff-save-permissions-unbounded-grant: a settings-only editor cannot grant finance to another administrator', function () {
    $editor = User::factory()->create();
    $editor->assignRole('admin');
    $editor->syncPermissions(['settings.manage']);
    $target = User::factory()->create();
    $target->assignRole('admin');

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['finance.manage'])
        ->call('savePermissions');

    expect($editor->fresh()->can('finance.manage'))->toBeFalse()
        ->and($target->fresh()->can('finance.manage'))->toBeFalse();
});

test('whip-directory-profile-owner-binding: mismatched profile ownership cannot approve a certificate or import products', function (string $mismatchKind) {
    // Replace the WHIP connection completely; these tables exist only in memory.
    config(['database.connections.whip' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('whip');
    $fake = DB::connection('whip');
    foreach ([
        'halal_applications' => 'id integer primary key, user_id integer, application_no text, status text, certification_body_id integer',
        'halal_application_certificates' => 'id integer primary key, application_id integer, certificate_no text, valid_from text, valid_to text, services_text text',
        'halal_application_company_profiles' => 'id integer primary key, application_id integer, user_id integer, company_name text, registration_number text',
        'halal_application_products' => 'id integer primary key, application_id integer, product_name text, brand_name text, barcode text',
        'certification_bodies' => 'id integer primary key, name text',
    ] as $table => $columns) {
        $fake->statement("create table {$table} ({$columns})");
    }
    $fake->table('certification_bodies')->insert(['id' => 1, 'name' => 'Dummy CB']);
    foreach ([1 => 10, 2 => 99] as $id => $profileOwner) {
        $fake->table('halal_applications')->insert([
            'id' => $id, 'user_id' => 10, 'application_no' => "DUMMY-{$id}",
            'status' => 'certified', 'certification_body_id' => 1,
        ]);
        $fake->table('halal_application_certificates')->insert([
            'id' => $id, 'application_id' => $id, 'certificate_no' => "DUMMY-{$id}",
            'valid_from' => now()->subMonth()->toDateString(), 'valid_to' => now()->addYear()->toDateString(),
        ]);
        $fake->table('halal_application_company_profiles')->insert([
            'id' => $id, 'application_id' => $id,
            'user_id' => $mismatchKind === 'owner' ? $profileOwner : 10,
            'company_name' => 'Dummy Foods',
            'registration_number' => $id === 2 && $mismatchKind === 'registration' ? 'OTHER-SSM' : 'DUMMY-SSM',
        ]);
        $fake->table('halal_application_products')->insert([
            'id' => $id, 'application_id' => $id, 'product_name' => "Dummy product {$id}",
        ]);
    }
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create([
        'user_id' => $seller->id, 'registration_no' => 'DUMMYSSM', 'registration_verified_at' => now(),
    ]);
    $category = Category::factory()->create();
    $baselineTransactionLevel = DB::connection()->transactionLevel();
    $whipReadTransactionLevels = [];
    DB::listen(function ($event) use (&$whipReadTransactionLevels) {
        if ($event->connectionName === 'whip') {
            $whipReadTransactionLevels[] = DB::connection()->transactionLevel();
        }
    });
    $directory = app(WhipDirectory::class);
    expect(array_map(fn ($c) => $c->certificateNo, $directory->forCompany('DUMMYSSM')))->toBe(['DUMMY-1']);
    expect($directory->check('DUMMY-1', 'DUMMYSSM')[0])->toBe('verified');

    $page = Livewire::actingAs($seller)->test(SellerCertificates::class);
    $page->call('linkWhipCertificate', 'DUMMY-1')->assertHasNoErrors();
    $owned = $store->halalCertificates()->where('number', 'DUMMY-1')->firstOrFail();
    $page->set('importCategoryId', $category->id)->call('importWhipProducts', $owned->id)->assertHasNoErrors();
    expect($store->products()->where('whip_product_id', 1)->exists())->toBeTrue();

    $page->call('linkWhipCertificate', 'DUMMY-2')->assertHasErrors(['whip'])
        ->assertSee(__('WHIP could not confirm this certificate. Upload it for manual review.'));
    $mismatch = $store->halalCertificates()->where('number', 'DUMMY-2')->first();

    expect([
        'certificate_approved' => $mismatch?->isApproved() ?? false,
        'product_imported' => $store->products()->where('whip_product_id', 2)->exists(),
    ])->toBe(['certificate_approved' => false, 'product_imported' => false]);

    // A previously linked local row must not bypass the import recheck.
    $legacy = new HalalCertificate([
        'store_id' => $store->id, 'number' => 'DUMMY-2', 'issuing_body' => 'JAKIM',
        'holder_name' => 'Dummy Foods', 'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(),
    ]);
    $legacy->forceFill(['source' => 'whip', 'whip_application_id' => 2])->save();
    $page->call('importWhipProducts', $legacy->id)->assertHasErrors(['whip']);
    expect($store->products()->where('whip_product_id', 2)->exists())->toBeFalse();

    Storage::fake('local');
    Livewire::actingAs($seller)->test(\App\Livewire\Seller\Certificates\Form::class, ['certificate' => $legacy])
        ->set('document', UploadedFile::fake()->create('certificate.pdf', 50, 'application/pdf'))
        ->call('save')->assertHasNoErrors();
    expect($legacy->fresh()->status)->toBe(CertificateStatus::Pending)
        ->and($legacy->fresh()->whip_answer)->toBe('mismatch')
        ->and(array_unique($whipReadTransactionLevels))->toBe([$baselineTransactionLevel]);
})->with(['owner', 'registration']);

test('staff permission edits allow held grants and the superadmin exception', function (bool $superadmin) {
    $editor = User::factory()->create(['is_superadmin' => $superadmin]);
    $editor->assignRole('admin');
    $editor->syncPermissions($superadmin ? ['settings.manage'] : ['settings.manage', 'finance.manage']);
    $target = User::factory()->create();
    $target->assignRole('admin');

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['finance.manage'])->call('savePermissions')->assertHasNoErrors();

    expect($target->fresh()->can('finance.manage'))->toBeTrue();
})->with([false, true]);

test('legacy browser sessions without password proof require a fresh login', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $id = Str::random(40);
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->id, 'last_activity' => time(),
        'payload' => base64_encode(json_encode([Auth::guard('web')->getName() => $user->id])),
    ]);
    $this->withCookie(config('session.cookie'), $id)->get(route('account.orders'))->assertRedirect(route('login'));
});

test('fresh browser login stores password proof and reaches the account', function () {
    $user = User::factory()->create(['password' => 'dummy-current-password']);
    Livewire::test(Login::class)
        ->set('email', $user->email)->set('password', 'dummy-current-password')->call('login')->assertHasNoErrors();
    expect(session()->has('password_hash_web'))->toBeTrue();
    $this->get(route('account.orders'))->assertOk();
});

test('ruled seller refusals have Malay copy and English fallback', function (string $message) {
    app()->setLocale('en');
    expect(__($message))->toBe($message);

    app()->setLocale('ms');
    expect(__($message))->not->toBe($message)->not->toBe('');
})->with([
    'missing publication certificate' => 'This category needs a halal certificate. Register yours under Halal certificates, then tick this product.',
    'expired publication certificate' => 'The halal certificate covering this product has expired. Renew it to keep the listing live.',
    'publication certificate under review' => 'The halal certificate covering this product is still being checked.',
    'unverified WHIP import' => 'WHIP could not confirm this certificate for your company. Nothing was imported.',
]);
