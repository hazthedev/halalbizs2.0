<?php

/**
 * QA pass — admin permission model + privilege escalation.
 *
 * The app has ZERO Laravel policies. Authorization for /admin is:
 *   EnsureAdmin (role + 2FA)  ->  per-route `can:<permission>`  ->  nothing else.
 * The `admin` role holds no permissions (RoleSeeder), so every section is a
 * per-person spatie grant, plus users.is_superadmin as a Gate::before bypass.
 *
 * These tests hunt for holes in that model. Everything asserts on rendered
 * HTTP responses or database rows — never on component internals.
 */

use App\Enums\DocumentStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Sellers\StoreDetail;
use App\Livewire\Admin\System\Staff;
use App\Livewire\Storefront\Auth\Login;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

// ── Fixtures (qa-prefixed: PHPUnit loads every test file, so global helper
//    names must not collide with the ones in tests/Feature/Admin/*) ─────────

function apAdmin(array $permissions = []): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions($permissions);

    return $user->fresh();
}

function apBuyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user->fresh();
}

/** @return array{0: User, 1: Store} */
function apSeller(): array
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    return [$user->fresh(), $store];
}

/**
 * routes/admin.php, transcribed. permission => route names it gates.
 * Parameterised routes are exercised separately (they need a bound model).
 */
function apGrid(): array
{
    return [
        'sellers.manage' => [
            'admin.sellers.applications', 'admin.sellers.stores', 'admin.buyers.index',
        ],
        'products.moderate' => [
            'admin.catalog.categories', 'admin.catalog.attributes', 'admin.catalog.brands',
            'admin.catalog.moderation', 'admin.catalog.reviews',
        ],
        'orders.manage' => [
            'admin.orders.index', 'admin.orders.returns', 'admin.payments.index',
            'admin.subscriptions.index',
        ],
        'finance.manage' => [
            'admin.finance.commission', 'admin.finance.payouts', 'admin.finance.boosts',
            'admin.coins.index', 'admin.affiliates.index',
        ],
        'cms.manage' => [
            'admin.content.banners', 'admin.content.home-sections', 'admin.content.pages',
            'admin.content.theme', 'admin.live.index', 'admin.content.flash-sales',
            'admin.support.articles', 'admin.support.tickets',
        ],
        'vouchers.manage' => ['admin.content.vouchers'],
        'localization.manage' => ['admin.localization'],
        'settings.manage' => [
            'admin.system.settings', 'admin.system.staff', 'admin.system.audit',
            'admin.system.search',
        ],
    ];
}

/** Routes inside /admin that carry NO can: gate at all. */
function apUngatedRoutes(): array
{
    return ['admin.dashboard', 'admin.notifications'];
}

/** Pull one component's snapshot JSON out of a rendered page. */
function apSnapshot(string $html, string $componentName): string
{
    preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $json = html_entity_decode($encoded, ENT_QUOTES);

        if (str_contains($json, '"'.$componentName.'"')) {
            return $json;
        }
    }

    throw new RuntimeException("No wire:snapshot found for [{$componentName}].");
}

/** Replay a Livewire action over the REAL /livewire/update endpoint. */
function apReplay(string $snapshot, string $method, array $params = [])
{
    return test()->withHeader('X-Livewire', 'true')->postJson(route('default-livewire.update'), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]],
    ]);
}

// ══════════════════════════════════════════════════════════════════════════
// 1 + 2 — the full permission grid
// ══════════════════════════════════════════════════════════════════════════

test('QA1: every single-permission admin reaches exactly its own section and nothing else', function () {
    $grid = apGrid();
    $allRoutes = collect($grid)->flatten()->all();
    $problems = [];

    foreach ($grid as $permission => $ownRoutes) {
        $admin = apAdmin([$permission]);

        foreach ($allRoutes as $route) {
            $status = test()->actingAs($admin)->get(route($route))->getStatusCode();
            $shouldPass = in_array($route, $ownRoutes, true);

            if ($shouldPass && $status === 403) {
                $problems[] = "{$permission} was 403'd out of its OWN route {$route}";
            }

            if (! $shouldPass && $status !== 403) {
                $problems[] = "{$permission} got {$status} (expected 403) on {$route}";
            }
        }
    }

    expect($problems)->toBe([]);
});

test('QA2: a zero-permission admin is 403 on every gated route but reaches the ungated ones', function () {
    $admin = apAdmin();
    $problems = [];

    foreach (collect(apGrid())->flatten() as $route) {
        $status = test()->actingAs($admin)->get(route($route))->getStatusCode();

        if ($status !== 403) {
            $problems[] = "zero-permission admin got {$status} on {$route}";
        }
    }

    foreach (apUngatedRoutes() as $route) {
        $status = test()->actingAs($admin)->get(route($route))->getStatusCode();

        if ($status !== 200) {
            $problems[] = "zero-permission admin got {$status} on ungated {$route}";
        }
    }

    expect($problems)->toBe([]);
});

test('QA3: parameterised admin routes are gated too', function () {
    [, $store] = apSeller();
    $buyer = apBuyer();
    $subOrder = SubOrder::factory()->create(['store_id' => $store->id]);

    $zero = apAdmin();

    test()->actingAs($zero)->get(route('admin.sellers.stores.show', $store))->assertForbidden();
    test()->actingAs($zero)->get(route('admin.buyers.show', $buyer))->assertForbidden();
    test()->actingAs($zero)->get(route('admin.orders.show', $subOrder))->assertForbidden();

    test()->actingAs(apAdmin(['sellers.manage']))
        ->get(route('admin.sellers.stores.show', $store))->assertOk();
    test()->actingAs(apAdmin(['orders.manage']))
        ->get(route('admin.orders.show', $subOrder))->assertOk();
});

// ══════════════════════════════════════════════════════════════════════════
// 1 (data) — what the ungated dashboard RENDERS for a zero-permission admin
// ══════════════════════════════════════════════════════════════════════════

test('QA4: the dashboard withholds category-level GMV money from an admin without finance.manage', function () {
    $category = Category::factory()->create(['name' => ['en' => 'Zamzam Dates', 'ms' => 'Zamzam Dates']]);
    [, $store] = apSeller();
    $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id]);

    $order = Order::factory()->create();
    $subOrder = SubOrder::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'status' => SubOrderStatus::Completed,
        'completed_at' => now(),
        'total_sen' => 4_242_00,
    ]);
    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'product_name' => 'Zamzam',
        'unit_price_sen' => 4_242_00,
        'qty' => 1,
        'line_total_sen' => 4_242_00,
    ]);

    $html = test()->actingAs(apAdmin())->get(route('admin.dashboard'))->getContent();

    // Pull every <x-ui.chart> payload, keep the one carrying the category series.
    preg_match_all('/hbChart\((.*?)\)"/s', $html, $m);
    $payload = collect($m[1])
        ->map(fn ($raw) => html_entity_decode($raw, ENT_QUOTES))
        ->first(fn ($decoded) => str_contains($decoded, 'Zamzam Dates')) ?? '';

    // 4242_00 sen -> the chart payload divides by 100 and ships the plain int.
    expect($payload)->toBe('');
})->group('leak');

test('QA5: the dashboard withholds lifetime order/user counts from an admin with no grants', function () {
    User::factory()->count(3)->create();
    $order = Order::factory()->create(['placed_at' => now()]);
    SubOrder::factory()->create(['order_id' => $order->id, 'status' => SubOrderStatus::Completed]);

    // ⚠ This asserted that the LABEL "Orders today" was absent, which is the
    // wrong target twice over: the leak is the NUMBER, and
    // SuperadminTest.php:158 deliberately requires the label to stay for every
    // admin ("the operational tiles every admin needs stay put"). No gate can
    // satisfy both. Assert the count instead — which is what the test name says.
    $tile = fn (string $html): string => preg_match(
        '/Orders today.*?tabular-nums[^>]*>\s*([\d,]+)\s*</s', $html, $m
    ) === 1 ? $m[1] : 'NO-TILE';

    // Control: an admin who IS allowed to see it gets the real figure. Without
    // this, a 0 below would not distinguish "withheld" from "nothing seeded".
    $granted = $tile(test()->actingAs(apAdmin(['orders.manage']))->get(route('admin.dashboard'))->getContent());

    $withheld = $tile(test()->actingAs(apAdmin())->get(route('admin.dashboard'))->getContent());

    expect($granted)->toBe('1')
        ->and($withheld)->toBe('0');
})->group('leak');

// ══════════════════════════════════════════════════════════════════════════
// 3 — is_superadmin
// ══════════════════════════════════════════════════════════════════════════

test('QA6: is_superadmin cannot be mass-assigned through any model write path', function () {
    $user = User::factory()->create();

    $user->update(['name' => 'x', 'is_superadmin' => true]);
    $user->fill(['is_superadmin' => true])->save();
    $created = User::create([
        'name' => 'y', 'email' => 'y@example.test', 'password' => 'secret', 'is_superadmin' => true,
    ]);

    expect($user->fresh()->is_superadmin)->toBeFalse()
        ->and($created->fresh()->is_superadmin)->toBeFalse();
});

test('QA7: a settings.manage admin cannot flip is_superadmin by tampering with the Staff component', function () {
    $attacker = apAdmin(['settings.manage']);
    $victim = apAdmin();

    // The escalation entry point, called with a raw id argument.
    Livewire::actingAs($attacker)
        ->test(Staff::class)
        ->call('toggleSuperadmin', $attacker->id)
        ->call('toggleSuperadmin', $victim->id);

    expect($attacker->fresh()->is_superadmin)->toBeFalse()
        ->and($victim->fresh()->is_superadmin)->toBeFalse();
});

test('QA8: editingId is #[Locked] so a settings.manage admin cannot retarget the permission form at themselves', function () {
    $attacker = apAdmin(['settings.manage']);

    Livewire::actingAs($attacker)
        ->test(Staff::class)
        ->set('editingId', $attacker->id);
})->throws(Exception::class);

test('QA9: a settings.manage admin cannot grant themselves finance.manage through savePermissions', function () {
    $attacker = apAdmin(['settings.manage']);
    $victim = apAdmin();

    Livewire::actingAs($attacker)
        ->test(Staff::class)
        ->call('editPermissions', $victim->id)
        ->set('editPermissions', ['finance.manage', 'settings.manage'])
        ->call('savePermissions');

    // The victim was legitimately granted; the attacker must be unchanged.
    expect($victim->fresh()->can('finance.manage'))->toBeTrue()
        ->and($attacker->fresh()->can('finance.manage'))->toBeFalse();
});

test('QA10: a settings.manage admin cannot mint themselves a fully-privileged account via invite', function () {
    $attacker = apAdmin(['settings.manage']);

    $component = Livewire::actingAs($attacker)
        ->test(Staff::class)
        ->set('inviteName', 'Second Me')
        ->set('inviteEmail', 'second.me@example.test')
        ->set('invitePermissions', RoleSeeder::ADMIN_PERMISSIONS)
        ->call('invite');

    $minted = User::where('email', 'second.me@example.test')->first();
    $password = $component->get('generatedPassword');

    // The attacker holds settings.manage only. If invite() hands them a working
    // login for an account holding finance.manage, the "no self-service grants"
    // control in editPermissions()/savePermissions() is bypassable in one step.
    expect([
        'minted_permissions' => $minted?->getDirectPermissions()->pluck('name')->sort()->values()->all(),
        'password_handed_to_attacker' => $password !== null,
        'minted_can_finance' => (bool) $minted?->can('finance.manage'),
    ])->toBe([
        'minted_permissions' => [],
        'password_handed_to_attacker' => false,
        'minted_can_finance' => false,
    ]);
})->group('escalation');

// ══════════════════════════════════════════════════════════════════════════
// 4 — privilege escalation by replay (real /livewire/update round trip)
// ══════════════════════════════════════════════════════════════════════════

test('QA11: a revoked permission kills an in-flight admin snapshot (moderation approve)', function () {
    $admin = apAdmin(['products.moderate']);
    $product = Product::factory()->create(['status' => ProductStatus::PendingReview]);

    $html = test()->actingAs($admin)->get(route('admin.catalog.moderation'))->getContent();
    $snapshot = apSnapshot($html, 'admin.catalog.moderation');

    // Permission pulled after the page was rendered.
    $admin->syncPermissions([]);

    test()->actingAs($admin->fresh());
    $response = apReplay($snapshot, 'approve', [$product->id]);

    expect($response->getStatusCode())->toBe(403)
        ->and($product->fresh()->status)->toBe(ProductStatus::PendingReview);
});

test('QA12: a buyer replaying a leaked admin snapshot cannot moderate a product', function () {
    $admin = apAdmin(['products.moderate']);
    $product = Product::factory()->create(['status' => ProductStatus::PendingReview]);

    $snapshot = apSnapshot(
        test()->actingAs($admin)->get(route('admin.catalog.moderation'))->getContent(),
        'admin.catalog.moderation'
    );

    test()->actingAs(apBuyer());
    $response = apReplay($snapshot, 'approve', [$product->id]);

    expect($response->getStatusCode())->toBe(403)
        ->and($product->fresh()->status)->toBe(ProductStatus::PendingReview);
});

test('QA13: a seller replaying a leaked admin snapshot cannot suspend a rival store', function () {
    [, $rival] = apSeller();
    [$seller] = apSeller();
    $admin = apAdmin(['sellers.manage']);

    $snapshot = apSnapshot(
        test()->actingAs($admin)->get(route('admin.sellers.stores.show', $rival))->getContent(),
        'admin.sellers.store-detail'
    );

    test()->actingAs($seller);
    apReplay($snapshot, 'saveCommission');

    expect($rival->fresh()->status)->toBe(StoreStatus::Approved);
});

test('QA14: a buyer replaying an UNGATED admin snapshot (dashboard) is refused', function () {
    // Snapshot minted by a FULL-privilege admin. Replaying it as a buyer and
    // getting a 200 back is the escalation; whether the re-render still shows
    // the finance-only numbers tells us whose identity the render used.
    $admin = apAdmin(['finance.manage']);

    // Distinctive platform data only an admin should ever see.
    [, $store] = apSeller();
    $category = Category::factory()->create(['name' => ['en' => 'Kurma Rahsia', 'ms' => 'Kurma Rahsia']]);
    $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id]);
    $subOrder = SubOrder::factory()->create([
        'order_id' => Order::factory()->create()->id,
        'store_id' => $store->id,
        'status' => SubOrderStatus::Completed,
        'completed_at' => now(),
    ]);
    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'product_name' => 'Kurma',
        'unit_price_sen' => 777_00,
        'qty' => 1,
        'line_total_sen' => 777_00,
    ]);

    $snapshot = apSnapshot(
        test()->actingAs($admin)->get(route('admin.dashboard'))->getContent(),
        'admin.dashboard'
    );

    test()->actingAs(apBuyer());
    $response = apReplay($snapshot, 'setPeriod', ['7d']);

    expect([
        'status' => $response->getStatusCode(),
        'body_leaks_category_gmv' => str_contains($response->getContent(), 'Kurma Rahsia'),
        'body_leaks_finance_tile' => str_contains($response->getContent(), 'Commission revenue'),
    ])->toBe([
        'status' => 403,
        'body_leaks_category_gmv' => false,
        'body_leaks_finance_tile' => false,
    ]);
})->group('escalation');

test('QA15: an ex-admin whose role was revoked cannot keep driving admin components', function () {
    $admin = apAdmin();

    $snapshot = apSnapshot(
        test()->actingAs($admin)->get(route('admin.dashboard'))->getContent(),
        'admin.dashboard'
    );

    $admin->removeRole('admin');

    test()->actingAs($admin->fresh());
    $response = apReplay($snapshot, 'setPeriod', ['7d']);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400);
})->group('escalation');

// ══════════════════════════════════════════════════════════════════════════
// 5 — suspended seller: login + buyer-side effect on their live products
// ══════════════════════════════════════════════════════════════════════════

test('QA16: suspending a store takes its products off the buyer-facing storefront', function () {
    [, $store] = apSeller();
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Live]);

    $admin = apAdmin(['sellers.manage']);

    Livewire::actingAs($admin)
        ->test(StoreDetail::class, ['store' => $store])
        ->set('suspendReason', 'Selling non-halal goods as halal.')
        ->call('suspend');

    expect($store->fresh()->status)->toBe(StoreStatus::Suspended);

    // ⚠ Log the admin OUT first. The original version of this test asserted
    // straight after Livewire::actingAs($admin), so its GET ran as the ADMIN and
    // hit ProductDetail's deliberate owner/admin preview branch (canPreview,
    // ProductDetail.php:135-144) — it was measuring the preview hatch, not a
    // buyer. It also expected a 'unlisted' status, which is not a member of
    // ProductStatus (draft|pending_review|live|delisted|banned) and would throw
    // on read. Both expectations were wrong; the defect underneath was real.
    auth()->logout();

    // Guest side: neither the store nor its products are reachable.
    test()->get(route('store.show', $store))->assertNotFound();
    test()->get(route('product.show', $product))->assertNotFound();

    // The product row itself is untouched — suspension is a STORE state, and
    // reinstating must not have to guess which products the seller delisted
    // themselves. Visibility is derived, not copied onto the child.
    expect($product->fresh()->status)->toBe(ProductStatus::Live);
})->group('moderation');

test('QA16b: a suspended store\'s product cannot be bought by a signed-in buyer', function () {
    [, $store] = apSeller();
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Live]);
    $buyer = apBuyer();

    // Control: before suspension the buyer CAN reach it. Without this, a 404
    // after suspension proves nothing — the fixture might never have worked.
    $this->actingAs($buyer)->get(route('product.show', $product))->assertOk();

    $admin = apAdmin(['sellers.manage']);
    Livewire::actingAs($admin)
        ->test(StoreDetail::class, ['store' => $store])
        ->set('suspendReason', 'Selling non-halal goods as halal.')
        ->call('suspend');

    $this->actingAs($buyer)->get(route('product.show', $product))->assertNotFound();
})->group('moderation');

test('QA17: a suspended store\'s products vanish from the catalogue listing', function () {
    [, $store] = apSeller();
    $product = Product::factory()->create([
        'store_id' => $store->id,
        'status' => ProductStatus::Live,
        'name' => ['en' => 'Suspendium Kurma', 'ms' => 'Suspendium Kurma'],
    ]);

    $store->update(['status' => StoreStatus::Suspended]);

    $html = test()->get(route('search', ['q' => 'Suspendium']))->getContent();

    expect($html)->not->toContain('Suspendium Kurma');
})->group('moderation');

test('QA18: a suspended USER cannot log in', function () {
    $user = User::factory()->create(['status' => 'suspended', 'password' => 'password']);
    $user->assignRole('buyer');

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(auth()->check())->toBeFalse();
});

// ══════════════════════════════════════════════════════════════════════════
// 6 — the KYC document route
// ══════════════════════════════════════════════════════════════════════════

test('QA19: the KYC document route denies buyer, seller and a zero-permission admin', function () {
    [, $store] = apSeller();
    [$otherSeller] = apSeller();
    $document = StoreDocument::create([
        'store_id' => $store->id,
        'type' => 'ic',
        'status' => DocumentStatus::Pending,
    ]);

    $url = route('admin.sellers.documents.show', $document);

    test()->get($url)->assertRedirect(route('admin.login'));
    test()->actingAs(apBuyer())->get($url)->assertForbidden();
    test()->actingAs($otherSeller)->get($url)->assertForbidden();
    test()->actingAs($store->user)->get($url)->assertForbidden(); // own document, seller role
    test()->actingAs(apAdmin())->get($url)->assertForbidden();
    test()->actingAs(apAdmin(['products.moderate']))->get($url)->assertForbidden();

    // The gate opens for sellers.manage — 404 here is the "no media attached"
    // branch inside the controller, i.e. authorization passed.
    test()->actingAs(apAdmin(['sellers.manage']))->get($url)->assertNotFound();
});
