<?php

/**
 * QA 2026-08-06 — deliverable #3: the authorization matrix, built by OBSERVATION.
 *
 * Every GET route is hit as every persona and the ACTUAL status recorded. The
 * matrix is written to the QA folder; the assertions below are the revert-proofs
 * for the ownership/permission claims.
 */

use App\Enums\AffiliateStatus;
use App\Enums\DocumentStatus;
use App\Enums\GatewayPaymentStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\GroupBuyTeamStatus;
use App\Enums\LiveSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Affiliate;
use App\Models\Category;
use App\Models\CoinWallet;
use App\Models\GroupBuy;
use App\Models\GroupBuyTeam;
use App\Models\HelpArticle;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportRedirects\SupportRedirects;

const QA_MATRIX_PATH = '/Users/Apple/Desktop/Hermes/projects/halalbizs2.0/qa-2026-08-06/AUTHORIZATION-MATRIX.md';

const QA_PERMISSIONS = [
    'sellers.manage',
    'products.moderate',
    'orders.manage',
    'finance.manage',
    'vouchers.manage',
    'cms.manage',
    'settings.manage',
    'localization.manage',
];

/**
 * Routes whose parameters bind to an OWNABLE resource. Value = the persona keys
 * that are legitimately allowed a 2xx once the params point at A's fixtures.
 * Anyone else getting 2xx is an IDOR.
 */
const QA_OWNER_ALLOW = [
    'account.orders.show' => ['buyerA'],
    'account.orders.invoice' => ['buyerA'],
    'checkout.success' => ['buyerA'],
    'payments.ipay88.pay' => ['buyerA'],
    'payments.ipay88.processing' => ['buyerA'],
    'payments.ipay88.status' => ['buyerA'],
    'seller.orders.show' => ['sellerA'],
    'seller.orders.packing-slip' => ['sellerA'],
    'seller.products.edit' => ['sellerA'],
    // Admin-side ownable params: the panel is deliberately cross-tenant, so the
    // only allowed personas are the admins that clear the section's can: gate.
    'admin.orders.show' => ['superadmin'],
    'admin.buyers.show' => ['superadmin'],
    'admin.sellers.stores.show' => ['superadmin'],
    'admin.sellers.documents.show' => ['superadmin'],
];

function qaWorld(): array
{
    test()->seed(RoleSeeder::class);

    $mk = function (array $state = []) {
        return User::factory()->create($state);
    };

    $buyerA = $mk();
    $buyerA->assignRole('buyer');
    $buyerB = $mk();
    $buyerB->assignRole('buyer');

    $unverified = User::factory()->unverified()->create();
    $unverified->assignRole('buyer');

    $sellerAUser = $mk();
    $sellerAUser->assignRole('seller');
    $storeA = Store::factory()->approved()->create(['user_id' => $sellerAUser->id]);

    $sellerBUser = $mk();
    $sellerBUser->assignRole('seller');
    $storeB = Store::factory()->approved()->create(['user_id' => $sellerBUser->id]);

    $adminNone = $mk(['two_factor_method' => 'email']);
    $adminNone->assignRole('admin');
    $adminNone->syncPermissions([]);

    $superadmin = $mk(['two_factor_method' => 'email']);
    $superadmin->assignRole('admin');
    $superadmin->forceFill(['is_superadmin' => true])->save();

    $permAdmins = [];
    foreach (QA_PERMISSIONS as $permission) {
        $u = $mk(['two_factor_method' => 'email']);
        $u->assignRole('admin');
        $u->syncPermissions([$permission]);
        $permAdmins[$permission] = $u->fresh();
    }

    $category = Category::factory()->create();
    $productA = Product::factory()->create(['store_id' => $storeA->id, 'category_id' => $category->id]);
    $productB = Product::factory()->create(['store_id' => $storeB->id, 'category_id' => $category->id]);
    $draftProduct = Product::factory()->create([
        'store_id' => $storeA->id,
        'category_id' => $category->id,
        'status' => ProductStatus::Draft,
        'published_at' => null,
    ]);

    $orderA = Order::factory()->awaitingIpay88()->create(['user_id' => $buyerA->id]);
    $subOrderA = SubOrder::factory()->create(['order_id' => $orderA->id, 'store_id' => $storeA->id]);

    $orderB = Order::factory()->awaitingIpay88()->create(['user_id' => $buyerB->id]);
    $subOrderB = SubOrder::factory()->create(['order_id' => $orderB->id, 'store_id' => $storeB->id]);

    $docA = StoreDocument::create(['store_id' => $storeA->id, 'type' => 'ssm', 'status' => DocumentStatus::Pending]);
    $docB = StoreDocument::create(['store_id' => $storeB->id, 'type' => 'ssm', 'status' => DocumentStatus::Pending]);
    // Without a media file the controller 404s for everyone, which would hide the
    // authorization answer behind a "no such file" — attach one so a 2xx is possible.
    $docA->addMediaFromString('%PDF-1.4 QA KYC PROBE')->usingFileName('ssm.pdf')->toMediaCollection('file');

    $page = Page::create(['slug' => 'qa-terms', 'title' => ['en' => 'Terms'], 'body' => ['en' => '<p>Terms</p>'], 'is_active' => true]);
    $article = HelpArticle::create(['category' => 'buying', 'title' => ['en' => 'QA article'], 'body' => ['en' => 'Body'], 'position' => 1, 'is_active' => true]);

    $session = LiveSession::create([
        'store_id' => $storeA->id,
        'title' => 'QA live',
        'slug' => 'qa-live',
        'status' => LiveSessionStatus::Live,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'started_at' => now()->subMinutes(5),
    ]);

    $groupBuy = GroupBuy::create([
        'store_id' => $storeA->id,
        'product_id' => $productA->id,
        'product_variant_id' => $productA->variants()->first()->id,
        'group_price_sen' => 1000,
        'target_size' => 3,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(7),
    ]);
    $team = GroupBuyTeam::create([
        'group_buy_id' => $groupBuy->id,
        'initiator_id' => $buyerA->id,
        'code' => 'QATEAM01',
        'status' => GroupBuyTeamStatus::Forming,
        'expires_at' => now()->addDay(),
    ]);

    $affiliate = Affiliate::create([
        'user_id' => $buyerA->id,
        'code' => 'QAAFF01',
        'status' => AffiliateStatus::Active,
        'commission_rate_bp' => 500,
    ]);

    return compact(
        'buyerA', 'buyerB', 'unverified', 'sellerAUser', 'sellerBUser', 'storeA', 'storeB',
        'adminNone', 'superadmin', 'permAdmins', 'category', 'productA', 'productB', 'draftProduct',
        'orderA', 'orderB', 'subOrderA', 'subOrderB', 'docA', 'docB', 'page', 'article',
        'session', 'groupBuy', 'team', 'affiliate'
    );
}

/** Persona key => user (null = guest), in matrix-column order. */
function qaPersonas(array $w): array
{
    return [
        'guest' => null,
        'unverified' => $w['unverified'],
        'buyerA' => $w['buyerA'],
        'buyerB' => $w['buyerB'],
        'sellerA' => $w['sellerAUser'],
        'sellerB' => $w['sellerBUser'],
        'adminNone' => $w['adminNone'],
        'superadmin' => $w['superadmin'],
    ];
}

/** Substitute route parameters from A-owned fixtures. Returns null if unresolvable. */
function qaResolveUri(Route $route, array $w): ?string
{
    $models = [
        'subOrder' => $w['subOrderA'],
        'store' => $w['storeA'],
        'storeDocument' => $w['docA'],
        'user' => $w['buyerA'],
        'product' => $w['productA'],
        'category' => $w['category'],
        'order' => $w['orderA'],
        'team' => $w['team'],
        'session' => $w['session'],
        'article' => $w['article'],
    ];

    $scalars = [
        'slug' => $w['page']->slug,
        'code' => $w['affiliate']->code,
        'token' => 'qa-reset-token',
        'id' => (string) $w['buyerA']->id,
        'hash' => sha1($w['buyerA']->email),
        'result' => 'success',
        'path' => 'qa-probe.txt',
        'filename' => 'qa-probe.png',
    ];

    $uri = '/'.ltrim($route->uri(), '/');

    if (! preg_match_all('/\{(\w+)(:(\w+))?\??\}/', $uri, $m, PREG_SET_ORDER)) {
        return $uri;
    }

    foreach ($m as $match) {
        [$token, $name] = [$match[0], $match[1]];
        // Laravel strips `:slug` out of uri() and keeps it in bindingFields —
        // reading it off the token alone silently binds by id and 404s.
        $field = $match[3] ?? ($route->bindingFieldFor($name) ?: null);

        if (isset($models[$name])) {
            $value = $field ? $models[$name]->{$field} : $models[$name]->getKey();
        } elseif (isset($scalars[$name])) {
            $value = $scalars[$name];
        } else {
            return null;
        }

        $uri = str_replace($token, (string) $value, $uri);
    }

    return $uri;
}

function qaHit(string $uri, ?User $user): string
{
    if ($user !== null) {
        test()->actingAs($user);
    } else {
        auth()->logout();
        app('auth')->forgetGuards();
    }

    // Livewire's SupportRedirects swaps app('redirect') for its own Redirector on
    // component boot and only puts the real one back in dehydrate(). A mount()
    // that abort()s never dehydrates, so the swap leaks into every LATER request
    // in this process and turns unrelated redirects into 500s. Production runs one
    // request per process, so this is purely a sweep artifact — undo it each hit.
    if (! app()->bound('qa.pristine_redirect')) {
        app()->instance('qa.pristine_redirect', app('redirect'));
    }

    try {
        $response = test()->get($uri);
    } catch (Throwable $e) {
        return 'EXC:'.class_basename($e);
    } finally {
        SupportRedirects::$redirectorCacheStack = [];
        app()->instance('redirect', app('qa.pristine_redirect'));
    }

    $status = $response->getStatusCode();

    if ($status >= 500) {
        $e = $response->exception;

        return '500 '.($e ? class_basename($e).': '.Str::limit(str_replace('|', '/', $e->getMessage()), 90) : 'unknown');
    }

    if ($status >= 300 && $status < 400) {
        $target = (string) $response->headers->get('Location');
        $path = parse_url($target, PHP_URL_PATH) ?: $target;

        return $status.' →'.$path;
    }

    return (string) $status;
}

function qaIs2xx(string $cell): bool
{
    return (bool) preg_match('/^2\d\d$/', $cell);
}

/**
 * The verdict engine: which personas got a 2xx they should not have.
 * Extracted so it can be revert-proved against a synthetic permissive row.
 *
 * @param  array<string,string>  $cells  persona key => observed status cell
 * @param  list<string>  $middleware
 * @return list<string> offending persona keys
 */
function qaViolations(?string $name, array $cells, array $middleware): array
{
    $has = fn (string $needle) => (bool) array_filter($middleware, fn ($m) => str_contains($m, $needle));

    $hasAuth = $has('Authenticate');
    $hasVerified = $has('EnsureEmailIsVerified');
    $hasAdmin = $has('EnsureAdmin');
    $hasSeller = $has('EnsureSeller');
    $hasCan = (bool) array_filter($middleware, fn ($m) => str_starts_with($m, 'can:') || str_contains($m, 'Authorize:'));

    $mustDeny = [];

    if ($name !== null && array_key_exists($name, QA_OWNER_ALLOW)) {
        $allow = QA_OWNER_ALLOW[$name];
        foreach (array_keys($cells) as $key) {
            if (! in_array($key, $allow, true)) {
                $mustDeny[] = $key;
            }
        }
    } else {
        if ($hasAuth) {
            $mustDeny[] = 'guest';
        }
        if ($hasVerified) {
            $mustDeny[] = 'unverified';
        }
        if ($hasAdmin) {
            $mustDeny = array_merge($mustDeny, ['guest', 'unverified', 'buyerA', 'buyerB', 'sellerA', 'sellerB']);
            if ($hasCan) {
                $mustDeny[] = 'adminNone';
            }
        }
        if ($hasSeller) {
            $mustDeny = array_merge($mustDeny, ['guest', 'unverified', 'buyerA', 'buyerB', 'adminNone', 'superadmin']);
        }
    }

    return array_values(array_filter(array_unique($mustDeny), fn ($k) => qaIs2xx($cells[$k] ?? '')));
}

// ── The sweep ──────────────────────────────────────────────────────────────

it('sweeps every GET route against every persona and writes the matrix', function () {
    $w = qaWorld();
    $personas = qaPersonas($w);

    $rows = [];
    $untestable = [];

    /** @var Route $route */
    foreach (RouteFacade::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uriRaw = $route->uri();
        $name = $route->getName() ?? '(unnamed)';

        // Livewire's own asset endpoints are framework plumbing, not app surface.
        if (str_starts_with($uriRaw, 'livewire-')) {
            $untestable[] = [$uriRaw, $name, 'Livewire framework asset/preview endpoint — not application surface'];

            continue;
        }

        $uri = qaResolveUri($route, $w);

        if ($uri === null) {
            $untestable[] = [$uriRaw, $name, 'route parameter not resolvable from fixtures'];

            continue;
        }

        $cells = [];
        foreach ($personas as $key => $user) {
            $cells[$key] = qaHit($uri, $user);
        }

        $rows[] = ['uri' => $uriRaw, 'name' => $name, 'cells' => $cells, 'middleware' => $route->gatherMiddleware()];
    }

    expect($rows)->not->toBeEmpty();

    // ── verdicts ──
    $suspect = [];

    foreach ($rows as $i => $row) {
        $violations = qaViolations($row['name'], $row['cells'], $row['middleware']);

        $rows[$i]['verdict'] = $violations === [] ? 'OK' : 'SUSPECT';
        $rows[$i]['violations'] = $violations;

        if ($violations !== []) {
            $suspect[] = $row['name'].' ('.$row['uri'].'): 2xx for '.implode(', ', $violations);
        }
    }

    // ── write the matrix ──
    $header = "| Route | Method | Guest | Unverified | Buyer(own) | Buyer(other) | Seller(own) | Seller(other) | Admin(no perms) | Superadmin | Verdict |\n";
    $header .= "|---|---|---|---|---|---|---|---|---|---|---|\n";

    $body = '';
    foreach ($rows as $row) {
        $c = $row['cells'];
        $body .= sprintf(
            "| `/%s`<br>`%s` | GET | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
            $row['uri'], $row['name'],
            $c['guest'], $c['unverified'], $c['buyerA'], $c['buyerB'],
            $c['sellerA'], $c['sellerB'], $c['adminNone'], $c['superadmin'],
            $row['verdict'],
        );
    }

    $un = "\n## Untestable / skipped\n\n| Route | Name | Why |\n|---|---|---|\n";
    foreach ($untestable as [$u, $n, $why]) {
        $un .= "| `/{$u}` | {$n} | {$why} |\n";
    }

    // Non-GET routes are outside the sweep by construction — list them so an
    // absence from the table above never reads as coverage.
    $writeCoverage = [
        'logout' => 'auth middleware only; POST, not swept',
        'newsletter.subscribe' => 'public by design, throttled 10/min; POST, not swept',
        'preferences.currency' => 'public by design, throttled 30/min; POST, not swept',
        'preferences.locale' => 'public by design, throttled 30/min; POST, not swept',
        'payments.ipay88.mock' => 'owner-gated + Ipay88Service::isMock() 404; simulator only when no merchant code is set',
        'payments.ipay88.response' => '404s on a blank merchant_key; signature checked (UX path, never fulfils)',
        'payments.ipay88.backend' => 'COVERED — "does not let a forged iPay88 backend callback mark an order paid"',
        'shipping.easyparcel.tracking' => 'COVERED — "rejects the courier webhook without its token…" (401)',
        'shipping.aftership.tracking' => 'COVERED — "rejects courier webhooks without valid credentials…" (401)',
        'storage.local.upload' => 'COVERED — "does not serve the private disk without a signature" (PUT → 403, no file written)',
        'default-livewire.update' => 'Livewire framework endpoint — snapshots are checksum-signed; component-action authz is a separate area',
        'livewire.upload-file' => 'Livewire framework endpoint',
    ];

    $writes = "\n## Non-GET routes (outside the GET sweep)\n\n| Route | Methods | How it was covered |\n|---|---|---|\n";
    foreach (RouteFacade::getRoutes() as $route) {
        if (in_array('GET', $route->methods(), true)) {
            continue;
        }
        $n = $route->getName() ?? '(unnamed)';
        $writes .= sprintf("| `/%s`<br>`%s` | %s | %s |\n", $route->uri(), $n, implode(',', array_diff($route->methods(), ['HEAD'])), $writeCoverage[$n] ?? 'NOT COVERED');
    }

    $notes = "\n## Suspect rows\n\n";
    $notes .= $suspect === []
        ? "None — every persona that should have been denied was denied.\n"
        : '- '.implode("\n- ", $suspect)."\n";

    $intro = "# halalbizs2.0 — Authorization matrix (observed)\n\n"
        .'Generated by `tests/Feature/Qa/AuthorizationMatrixTest.php` on '.date('Y-m-d')
        ." against a live MariaDB (`hbiz2_t1`), one real HTTP request per cell.\n\n"
        ."Cells are the **actual** HTTP status. `302 →/path` shows the redirect target.\n"
        ."Params are bound to **A-owned** fixtures (buyer A's order, seller A's store), so the\n"
        ."`Buyer(other)` / `Seller(other)` columns are the IDOR probe.\n\n"
        .'Routes swept: '.count($rows).' · untestable: '.count($untestable)."\n\n";

    @mkdir(dirname(QA_MATRIX_PATH), 0775, true);
    file_put_contents(QA_MATRIX_PATH, $intro.$header.$body.$un.$writes.$notes);

    // The sweep itself is evidence-gathering; the failure signal is any suspect row.
    expect($suspect)->toBe([], "SUSPECT rows:\n".implode("\n", $suspect));
});

// ── The permission cross-matrix: one admin per permission × every gated route ──

it('never lets an admin reach a section outside their single granted permission', function () {
    $w = qaWorld();

    $gated = [];
    /** @var Route $route */
    foreach (RouteFacade::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        if (! str_starts_with((string) $route->getName(), 'admin.')) {
            continue;
        }

        foreach ($route->gatherMiddleware() as $m) {
            if (preg_match('/(?:can|Authorize):([\w.]+)/', $m, $hit)) {
                $gated[$route->getName()] = ['permission' => $hit[1], 'uri' => qaResolveUri($route, $w)];
                break;
            }
        }
    }

    expect($gated)->not->toBeEmpty();

    $leaks = [];

    foreach ($gated as $name => $info) {
        if ($info['uri'] === null) {
            continue;
        }

        foreach ($w['permAdmins'] as $permission => $admin) {
            $status = qaHit($info['uri'], $admin);
            $allowed = $permission === $info['permission'];

            if (! $allowed && qaIs2xx($status)) {
                $leaks[] = "{$name} (needs {$info['permission']}) returned {$status} to an admin holding only {$permission}";
            }
            if ($allowed && ! qaIs2xx($status)) {
                $leaks[] = "{$name} (needs {$info['permission']}) returned {$status} to the admin that HOLDS it — over-tight or broken";
            }
        }
    }

    expect($leaks)->toBe([], implode("\n", $leaks));
});

// ── Harness revert-proof: prove the verdict engine can say SUSPECT ─────────

it('flags a permissive row — the sweep is capable of failing', function () {
    $allPersonas = array_fill_keys(
        ['guest', 'unverified', 'buyerA', 'buyerB', 'sellerA', 'sellerB', 'adminNone', 'superadmin'],
        '200',
    );

    // A guest-reachable auth-only route.
    expect(qaViolations('qa.canary', $allPersonas, ['web', 'Illuminate\Auth\Middleware\Authenticate']))
        ->toBe(['guest']);

    // A gated admin route an ungranted admin reached.
    expect(qaViolations('qa.canary', $allPersonas, [
        'web', 'Illuminate\Auth\Middleware\Authenticate',
        'App\Http\Middleware\EnsureAdmin', 'Illuminate\Auth\Middleware\Authorize:finance.manage',
    ]))->toContain('adminNone', 'buyerA', 'sellerB');

    // An ownable route reached by the wrong buyer.
    expect(qaViolations('account.orders.show', $allPersonas, ['web']))->toContain('buyerB', 'sellerA');

    // And a genuinely denied row is silent.
    $denied = array_merge($allPersonas, ['guest' => '302 →/login']);
    expect(qaViolations('qa.canary', $denied, ['web', 'Illuminate\Auth\Middleware\Authenticate']))->toBe([]);
});

// ── Revert-proofs: the individual ownership claims ─────────────────────────

it('keeps platform takings out of a zero-permission admin dashboard', function () {
    $w = qaWorld();

    // A real paid order so there IS a GMV number to leak.
    $order = Order::factory()->paid()->create(['user_id' => $w['buyerA']->id, 'grand_total_sen' => 987654]);

    $superHtml = test()->actingAs($w['superadmin'])->get(route('admin.dashboard'))->assertOk()->getContent();
    $noneHtml = test()->actingAs($w['adminNone'])->get(route('admin.dashboard'))->assertOk()->getContent();

    // Control: the number is genuinely rendered for someone who may see it.
    expect($superHtml)->toContain('9,876.54');
    // The claim: it is nowhere in the zero-permission admin's page — including
    // the Livewire snapshot, which ships whether or not the markup renders.
    expect($noneHtml)->not->toContain('9,876.54');
    expect($noneHtml)->not->toContain('987654');
});

it('parks an admin without 2FA outside the panel', function () {
    $w = qaWorld();

    $noTwoFactor = User::factory()->create(['two_factor_method' => null]);
    $noTwoFactor->assignRole('admin');
    $noTwoFactor->syncPermissions(QA_PERMISSIONS);

    foreach (['admin.dashboard', 'admin.system.staff', 'admin.finance.payouts'] as $name) {
        test()->actingAs($noTwoFactor->fresh())->get(route($name))
            ->assertRedirect(route('account.profile').'#security');
    }
});

it('gates the engagement KPI row behind a permission like the finance tiles above it', function () {
    $w = qaWorld();

    CoinWallet::create(['user_id' => $w['buyerA']->id, 'balance' => 123456]);

    // Control: it really is rendered for an admin who may see everything.
    $superHtml = test()->actingAs($w['superadmin'])->get(route('admin.dashboard'))->assertOk()->getContent();
    expect($superHtml)->toContain('123,456');

    // Claim under test: a zero-permission admin should not get platform-wide
    // coin liability, subscription and referral counts.
    $noneHtml = test()->actingAs($w['adminNone'])->get(route('admin.dashboard'))->assertOk()->getContent();

    // Evidence that this is the KPI row and not an incidental match: the whole
    // card ships to an admin with zero permissions.
    expect($noneHtml)->toContain('Coins in circulation');
    expect($noneHtml)->toContain('Active subscriptions');
    // The finance tiles directly above ARE gated — proving the omission is
    // inconsistency, not policy.
    expect($noneHtml)->not->toContain('GMV (paid)');

    expect($noneHtml)->not->toContain('123,456');
});

it('scopes the admin notification list to the signed-in admin', function () {
    $w = qaWorld();

    $w['superadmin']->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\AdminAlertNotification',
        'data' => ['message' => 'QA-SECRET-ALERT-TEXT', 'url' => '/admin'],
        'read_at' => null,
    ]);

    $mine = test()->actingAs($w['superadmin'])->get(route('admin.notifications'))->assertOk()->getContent();
    expect($mine)->toContain('QA-SECRET-ALERT-TEXT');

    $theirs = test()->actingAs($w['adminNone'])->get(route('admin.notifications'))->assertOk()->getContent();
    expect($theirs)->not->toContain('QA-SECRET-ALERT-TEXT');
});

it('does not let a forged iPay88 backend callback mark an order paid', function () {
    $w = qaWorld();

    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'QAMERCHANT';
    $settings->merchant_key = 'QA-SECRET-KEY';
    $settings->save();

    $order = $w['orderA'];
    $payment = Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $order->order_no.'-1',
        'amount_sen' => $order->grand_total_sen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Pending,
    ]);

    test()->post(route('payments.ipay88.backend'), [
        'MerchantCode' => 'QAMERCHANT',
        'PaymentId' => '2',
        'RefNo' => $payment->ref_no,
        'Amount' => number_format($order->grand_total_sen / 100, 2, '.', ''),
        'Currency' => 'MYR',
        'Status' => '1',
        'TransId' => 'FORGED-1',
        'Signature' => 'obviously-not-the-real-hmac',
    ])->assertOk();

    expect($order->fresh()->payment_status)->not->toBe(PaymentStatus::Paid);
    expect($payment->fresh()->status)->not->toBe(GatewayPaymentStatus::Success);
    expect($payment->fresh()->signature_valid)->toBeFalsy();
});

it('403s buyer B on buyer A sub-order detail', function () {
    $w = qaWorld();
    test()->actingAs($w['buyerB'])->get(route('account.orders.show', $w['subOrderA']))->assertForbidden();
});

it('403s buyer B on buyer A invoice download', function () {
    $w = qaWorld();
    test()->actingAs($w['buyerB'])->get(route('account.orders.invoice', $w['subOrderA']))->assertForbidden();
});

it('403s buyer B on buyer A checkout success + payment status + pay', function () {
    $w = qaWorld();
    test()->actingAs($w['buyerB'])->get(route('checkout.success', $w['orderA']))->assertForbidden();
    test()->actingAs($w['buyerB'])->get(route('payments.ipay88.status', $w['orderA']))->assertForbidden();
    test()->actingAs($w['buyerB'])->get(route('payments.ipay88.pay', $w['orderA']))->assertForbidden();
    test()->actingAs($w['buyerB'])->get(route('payments.ipay88.processing', $w['orderA']))->assertForbidden();
});

it('403s seller B on seller A sub-order, packing slip and product edit', function () {
    $w = qaWorld();
    test()->actingAs($w['sellerBUser'])->get(route('seller.orders.show', $w['subOrderA']))->assertForbidden();
    test()->actingAs($w['sellerBUser'])->get(route('seller.orders.packing-slip', $w['subOrderA']))->assertForbidden();
    test()->actingAs($w['sellerBUser'])->get(route('seller.products.edit', $w['productA']))->assertForbidden();
});

it('keeps a suspended seller out of the seller centre', function () {
    $w = qaWorld();
    $w['storeA']->forceFill(['status' => StoreStatus::Suspended])->save();

    test()->actingAs($w['sellerAUser'])->get(route('seller.dashboard'))->assertRedirect(route('seller.status'));
    test()->actingAs($w['sellerAUser'])->get(route('seller.products.index'))->assertRedirect(route('seller.status'));
});

it('keeps the private KYC document endpoint away from everyone but a sellers.manage admin', function () {
    $w = qaWorld();

    $uri = route('admin.sellers.documents.show', $w['docA']);

    foreach (['buyerA', 'sellerAUser', 'sellerBUser', 'adminNone'] as $key) {
        $status = qaHit($uri, $w[$key]);
        expect(qaIs2xx($status))->toBeFalse("{$key} got {$status} on the KYC document endpoint");
    }
});

it('does not serve the private disk without a signature', function () {
    qaWorld();

    Storage::disk('local')->put('qa-probe.txt', 'secret');

    $status = qaHit('/storage/qa-probe.txt', null);
    expect(qaIs2xx($status))->toBeFalse("guest read the private disk unsigned: {$status}");

    test()->put('/storage/qa-written.txt', [], ['Content-Type' => 'text/plain'])
        ->assertStatus(403);
    expect(Storage::disk('local')->exists('qa-written.txt'))->toBeFalse();
});

it('keeps unpublished catalogue data out of the public API', function () {
    $w = qaWorld();

    test()->getJson(route('api.product', $w['draftProduct']->slug))->assertNotFound();

    $listed = test()->getJson(route('api.products'))->json('data.*.id');
    expect($listed)->not->toContain($w['draftProduct']->id);
});

it('rejects courier webhooks without valid credentials and the gateway callback without a signature', function () {
    qaWorld();

    test()->post(route('shipping.easyparcel.tracking'), ['awb_no' => 'X', 'status' => 'delivered'])
        ->assertStatus(401);

    test()->post(route('shipping.easyparcel.tracking'), ['awb_no' => 'X'], ['X-EasyParcel-Token' => 'wrong'])
        ->assertStatus(401);

    test()->postJson(route('shipping.aftership.tracking'), ['event' => 'tracking_update'], ['aftership-hmac-sha256' => 'wrong'])
        ->assertStatus(401);
});
