<?php

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Livewire\Admin\System\Settings;
use App\Models\HalalCertificate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Settings\GeneralSettings;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/../../Support/WhipFixtures.php';

beforeEach(function () {
    $this->freezeTime();
    createWhipTables();
});

function mcpCall(string $name, array $arguments = [])
{
    return test()->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => (object) $arguments],
    ], ['Accept' => 'application/json, text/event-stream']);
}

test('public check_certificate returns active for a WHIP-certified, in-date certificate', function () {
    whipCert();
    $settings = app(GeneralSettings::class);
    $settings->mcp_public_enabled = true;
    $settings->save();

    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])
        ->assertOk()->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.status', 'active');
});

test('public access is off by default and the admin settings form persists both choices', function () {
    test()->seed([RoleSeeder::class, CurrencySeeder::class]);
    $admin = makeAdmin(User::factory()->create());
    expect(app(GeneralSettings::class)->mcp_public_enabled)->toBeFalse();
    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])
        ->assertJsonPath('result.isError', true)->assertSee('disabled');

    foreach ([true, false] as $enabled) {
        Livewire::actingAs($admin)->test(Settings::class)
            ->assertSee('Allow public AI assistants to check halal information')
            ->set('mcpPublicEnabled', $enabled)->call('saveGeneral')->assertHasNoErrors();
        expect(app(GeneralSettings::class)->mcp_public_enabled)->toBe($enabled);
    }
});

function mcpAdminToken(): string
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user->createToken('mcp test')->plainTextToken;
}

test('certificate queue requires an admin bearer token even while the public side is off', function () {
    mcpCall('certificate_queue')->assertJsonPath('error.code', -32602);
    $buyer = User::factory()->create();
    $this->withToken($buyer->createToken('buyer')->plainTextToken);
    mcpCall('certificate_queue')->assertJsonPath('error.code', -32602);
    $this->withToken(mcpAdminToken());
    mcpCall('certificate_queue')->assertOk()->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.counts.pending', 0)
        ->assertJsonPath('result.structuredContent.oldest_pending_age_seconds', null);
});

function mcpCertificate(array $attributes = []): HalalCertificate
{
    $cert = new HalalCertificate;
    $cert->forceFill($attributes + [
        'store_id' => Store::factory()->approved()->create()->id,
        'number' => fake()->unique()->numerify('MCP-#####'),
        'issuing_body' => 'JAKIM', 'holder_name' => 'Private holder',
        'valid_from' => now()->subMonth(), 'valid_to' => now()->addYear(),
        'status' => 'pending', 'submitted_at' => now()->subHours(2),
    ])->save();

    return $cert;
}

test('seller certificates can be read by store id or slug with source sync time and unmatched products', function () {
    $cert = mcpCertificate(['source' => 'whip', 'whip_synced_at' => now()]);
    $product = Product::factory()->create(['store_id' => $cert->store_id, 'name' => 'Unmatched tea']);
    $cert->forceFill(['whip_unmatched_product_ids' => [$product->id]])->save();
    $other = mcpCertificate();
    $this->withToken(mcpAdminToken());

    foreach ([(string) $cert->store_id, $cert->store->slug] as $store) {
        mcpCall('seller_certificates', ['store' => $store])->assertOk()
            ->assertJsonCount(1, 'result.structuredContent.certificates')
            ->assertJsonPath('result.structuredContent.certificates.0.status', 'pending')
            ->assertJsonPath('result.structuredContent.certificates.0.source', 'whip')
            ->assertJsonPath('result.structuredContent.certificates.0.whip_synced_at', $cert->whip_synced_at->toIso8601String())
            ->assertJsonPath('result.structuredContent.certificates.0.unmatched_products.0.name', 'Unmatched tea')
            ->assertDontSee($other->number);
    }
    mcpCall('certificate_queue')->assertJsonPath('result.structuredContent.counts.pending', 2)
        ->assertJsonPath('result.structuredContent.oldest_pending_age_seconds', 7200);
});

test('orders summary groups exact integer totals by payment status within a Malaysia calendar day', function () {
    Order::factory()->paid()->create(['placed_at' => '2026-11-11 16:00:00', 'grand_total_sen' => 12345]);
    Order::factory()->paid()->create(['placed_at' => '2026-11-12 15:59:59', 'grand_total_sen' => 100]);
    Order::factory()->create(['placed_at' => '2026-11-12 00:00:00', 'grand_total_sen' => 200]);
    Order::factory()->paid()->create(['placed_at' => '2026-11-11 15:59:59', 'grand_total_sen' => 99999]);
    Order::factory()->paid()->create(['placed_at' => '2026-11-12 16:00:00', 'grand_total_sen' => 99999]);
    $this->withToken(mcpAdminToken());

    $response = mcpCall('orders_summary', ['date' => '2026-11-12'])->assertOk()
        ->assertJsonPath('result.structuredContent.by_status.paid', ['count' => 2, 'total_sen' => 12445])
        ->assertJsonPath('result.structuredContent.by_status.pending', ['count' => 1, 'total_sen' => 200]);
    expect($response->getContent())->not->toContain('shipping_address', 'recipient_name', 'user_id', 'phone');
    mcpCall('orders_summary', ['date' => '2026-02-30'])->assertJsonPath('result.isError', true);
});

function mcpPublicOn(): void
{
    $settings = app(GeneralSettings::class);
    $settings->mcp_public_enabled = true;
    $settings->save();
}

test('public certificate results contain only the approved fields for every state', function (array $fixture, string $status) {
    mcpPublicOn();
    if ($status !== 'not_found') {
        whipCert($fixture);
    }
    $response = mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertOk()
        ->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.status', $status);
    expect(array_keys($response->json('result.structuredContent')))->toBe([
        'status', 'company_name', 'certification_body', 'valid_from', 'valid_to',
    ])->and($response->getContent())->not->toContain('registration', '202001-000123', 'address', 'document', 'application_id');
})->with([
    'active' => [[], 'active'],
    'expired' => [['to' => '2000-01-01'], 'expired'],
    'not certified' => [['status' => 'Rejected'], 'not_certified'],
    'future' => [['from' => '2099-01-01'], 'not_certified'],
    'missing' => [[], 'not_found'],
]);

test('ambiguous and unavailable WHIP return safe errors and invalid arguments are refused', function () {
    mcpPublicOn();
    whipCert();
    whipCert();
    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertJsonPath('result.isError', true)->assertSee('conflicting');
    Schema::connection('whip')->drop('halal_application_certificates');
    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertJsonPath('result.isError', true)
        ->assertSee('unavailable')->assertDontSee('SQLSTATE');
    foreach ([[], ['certificate_no' => ['WH-100']], ['certificate_no' => str_repeat('X', 256)]] as $arguments) {
        mcpCall('check_certificate', $arguments)->assertJsonPath('result.isError', true);
    }
});

test('all internal tools refuse guests non-admin invalid expired revoked and suspended tokens and session cookies', function () {
    $adminToken = mcpAdminToken();
    $access = PersonalAccessToken::findToken($adminToken);
    $admin = $access->tokenable;
    $expired = $admin->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
    $revoked = $admin->createToken('revoked')->plainTextToken;
    PersonalAccessToken::findToken($revoked)->delete();
    $buyer = User::factory()->create()->createToken('buyer')->plainTextToken;
    $suspended = mcpAdminToken();
    PersonalAccessToken::findToken($suspended)->tokenable->forceFill(['status' => 'suspended'])->save();
    $this->actingAs($admin); // A browser admin session alone must never count.
    foreach (['', $buyer, 'invalid', $expired, $revoked, $suspended] as $token) {
        $this->withToken($token);
        foreach (['seller_certificates', 'certificate_queue', 'orders_summary'] as $name) {
            mcpCall($name)->assertJsonPath('error.code', -32602);
        }
    }
});

test('mcp authenticates and reads without any database writes including token timestamps', function () {
    mcpPublicOn();
    whipCert();
    $cert = mcpCertificate();
    $token = mcpAdminToken();
    $this->withToken($token);
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertJsonPath('result.isError', false);
    mcpCall('seller_certificates', ['store' => (string) $cert->store_id])->assertJsonPath('result.isError', false);
    mcpCall('certificate_queue')->assertJsonPath('result.isError', false);
    mcpCall('orders_summary', ['date' => '2026-11-12'])->assertJsonPath('result.isError', false);
    mcpCall('find_halal_product', ['query' => 'tea'])->assertJsonPath('result.isError', false);
    expect($writes)->toBe([]);
    expect(PersonalAccessToken::findToken($token)->last_used_at)->toBeNull();
});

test('public requests are limited to thirty per minute per IP', function () {
    for ($i = 0; $i < 30; $i++) {
        mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertOk();
    }
    mcpCall('check_certificate', ['certificate_no' => 'WH-100'])->assertStatus(429);
});

test('product search returns only live verified HalalBizs listings by query or exact barcode', function () {
    mcpPublicOn();
    $certificate = mcpCertificate(['status' => 'approved']);
    $product = Product::factory()->create([
        'store_id' => $certificate->store_id, 'name' => 'Certified tea',
        'status' => ProductStatus::Live, 'halal_certificate_id' => $certificate->id,
    ]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => '00955123']);
    Product::factory()->create(['store_id' => $certificate->store_id, 'name' => 'Certified tea draft', 'status' => ProductStatus::Draft, 'halal_certificate_id' => $certificate->id]);
    Product::factory()->create(['name' => 'Unverified tea', 'status' => ProductStatus::Live]);

    foreach ([['query' => 'tea'], ['barcode' => '00955123']] as $arguments) {
        mcpCall('find_halal_product', $arguments)->assertOk()->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.products', [[
                'name' => 'Certified tea', 'store_name' => $certificate->store->name,
                'url' => route('product.show', $product->slug),
            ]]);
    }
    mcpCall('find_halal_product', ['barcode' => '955123'])->assertJsonPath('result.structuredContent.products', []);
});

test('product search caps after verification and excludes expired stale and hidden listings', function () {
    mcpPublicOn();
    $valid = mcpCertificate(['status' => 'approved']);
    Product::factory()->count(21)->create(['store_id' => $valid->store_id, 'name' => 'Tea without certificate']);
    Product::factory()->count(21)->create(['store_id' => $valid->store_id, 'name' => 'Tea verified', 'halal_certificate_id' => $valid->id]);
    $expired = mcpCertificate(['status' => 'approved', 'valid_to' => now()->subDay()]);
    $stale = mcpCertificate(['status' => 'approved', 'source' => 'whip', 'whip_synced_at' => now()->subHours(25)]);
    foreach ([$expired, $stale] as $certificate) {
        Product::factory()->create(['store_id' => $certificate->store_id, 'name' => 'Excluded tea', 'halal_certificate_id' => $certificate->id]);
    }
    mcpCall('find_halal_product', ['query' => 'tea'])->assertJsonCount(20, 'result.structuredContent.products');
    mcpCall('find_halal_product', ['query' => 'Excluded'])->assertJsonPath('result.structuredContent.products', []);
    $valid->store->forceFill(['status' => StoreStatus::Suspended])->save();
    mcpCall('find_halal_product', ['query' => 'tea'])->assertJsonPath('result.structuredContent.products', []);
});

test('both public tools are refused when off and product arguments must specify one bounded search', function () {
    mcpCall('find_halal_product', ['query' => 'tea'])->assertJsonPath('result.isError', true)->assertSee('disabled');
    mcpPublicOn();
    foreach ([[], ['query' => 'x'], ['barcode' => 955123], ['query' => ['tea']], ['query' => 'tea', 'barcode' => '955123'], ['query' => str_repeat('x', 201)]] as $arguments) {
        mcpCall('find_halal_product', $arguments)->assertJsonPath('result.isError', true);
    }
});

function mcpList()
{
    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream']);
}

test('discovery lists only public tools for guests and all five read-only tools for admins', function () {
    $public = mcpList()->assertOk()->json('result.tools');
    expect(array_column($public, 'name'))->toBe(['check_certificate', 'find_halal_product']);
    $this->withToken(mcpAdminToken());
    $tools = mcpList()->assertOk()->json('result.tools');
    expect(array_column($tools, 'name'))->toBe(['check_certificate', 'find_halal_product', 'certificate_queue', 'seller_certificates', 'orders_summary']);
    foreach ($tools as $tool) {
        expect($tool['annotations']['readOnlyHint'])->toBeTrue();
    }
});

test('queue age includes pending rows without a submission timestamp', function () {
    mcpCertificate(['submitted_at' => null, 'created_at' => now()->subDays(2)]);
    mcpCertificate(['status' => 'approved']);
    mcpCertificate(['status' => 'rejected']);
    $this->withToken(mcpAdminToken());
    mcpCall('certificate_queue')->assertJsonPath('result.structuredContent.counts', ['pending' => 1, 'approved' => 1, 'rejected' => 1])
        ->assertJsonPath('result.structuredContent.oldest_pending_age_seconds', 172800);
});
