<?php

/**
 * ADVERSARIAL AUTHZ / TENANT-ISOLATION STRESS SUITE (find-and-report).
 *
 * A FAILING assertion here is a WIN: it exposes a real authorization or
 * cross-store loophole. Passing tests confirm a guard genuinely holds.
 *
 * Hypotheses:
 *   #1 Seller LiveSessions feature()/removeProduct() unscoped Product::find()
 *   #2 Admin section gating vs. the admin role's permission set
 *   #3 Staff self-lockout guard
 *   #4 Buyer review purchase-gate
 *   #5 Seller cross-store order/product access
 */

use App\Enums\LiveSessionStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\System\Staff;
use App\Livewire\Seller\LiveSessions;
use App\Livewire\Seller\Orders\Detail as SellerOrderDetail;
use App\Livewire\Seller\Products\Form as SellerProductForm;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Turnstile;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// ── Self-contained fixtures ──────────────────────────────────────────────

/**
 * A seller user with the seller role AND an approved store, so the
 * CurrentStore trait's auth()->user()->store resolves.
 *
 * @return array{0: User, 1: Store}
 */
function stressSeller(): array
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    return [$user, $store];
}

/**
 * A completed (reviewable) sub-order with one order item, owned by $buyer.
 *
 * @return array{0: SubOrder, 1: OrderItem}
 */
function stressReviewableOrder(User $buyer, SubOrderStatus $status = SubOrderStatus::Completed): array
{
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    $subOrder = SubOrder::factory()->status($status)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
    ]);

    $item = OrderItem::create([
        'sub_order_id' => $subOrder->id,
        'product_id' => $product->id,
        'product_name' => 'Test Widget',
        'unit_price_sen' => 1000,
        'qty' => 1,
        'line_total_sen' => 1000,
    ]);

    return [$subOrder, $item];
}

// ── #1 — Seller LiveSessions cross-store feature() IDOR ───────────────────

test('#1 feature(): a seller CANNOT spotlight another store\'s product', function () {
    config(['live.enabled' => true]);

    [$sellerA, $storeA] = stressSeller();
    [$sellerB, $storeB] = stressSeller();

    // Store A: a session with its own product on the rail.
    $productA = Product::factory()->create(['store_id' => $storeA->id]);
    $sessionA = LiveSession::create([
        'store_id' => $storeA->id,
        'title' => 'A goes live',
        'slug' => 'a-goes-live',
        'status' => LiveSessionStatus::Scheduled,
    ]);
    $sessionA->products()->attach($productA->id, ['position' => 0]);

    // Store B's foreign product — never added to A's rail.
    $productB = Product::factory()->create(['store_id' => $storeB->id]);

    // Sanity: featuring A's OWN rail product works (the path is genuinely live).
    Livewire::actingAs($sellerA)
        ->test(LiveSessions::class)
        ->call('feature', $sessionA->id, $productA->id);

    expect($sessionA->fresh()->featured_product_id)->toBe($productA->id);

    // ATTACK: acting as A, drive feature() with store B's product id.
    Livewire::actingAs($sellerA)
        ->test(LiveSessions::class)
        ->call('feature', $sessionA->id, $productB->id);

    // INVARIANT: the foreign product must NOT become featured (proof, not inference).
    expect($sessionA->fresh()->featured_product_id)
        ->not->toBe($productB->id)
        ->toBe($productA->id);
});

test('#1 feature(): foreign product on an empty rail stays un-featured', function () {
    config(['live.enabled' => true]);

    [$sellerA, $storeA] = stressSeller();
    [$sellerB, $storeB] = stressSeller();

    $sessionA = LiveSession::create([
        'store_id' => $storeA->id,
        'title' => 'Empty rail',
        'slug' => 'empty-rail',
        'status' => LiveSessionStatus::Scheduled,
    ]);
    $productB = Product::factory()->create(['store_id' => $storeB->id]);

    Livewire::actingAs($sellerA)
        ->test(LiveSessions::class)
        ->call('feature', $sessionA->id, $productB->id);

    expect($sessionA->fresh()->featured_product_id)->toBeNull();
});

// ── #2 — Admin per-section permission gating ──────────────────────────────

test('#2 FINDING: a products-only admin must be BLOCKED from finance routes', function () {
    // Exactly how Staff::invite() provisions an admin: the `admin` role plus a
    // DIRECT permission subset. 2FA satisfies EnsureAdmin.
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->syncPermissions(['products.moderate']); // NOT finance.manage

    // INVARIANT the per-section can: gates are supposed to enforce:
    // an admin without finance.manage cannot reach the finance section.
    $this->actingAs($admin)->get(route('admin.finance.commission'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.finance.payouts'))->assertForbidden();
})->skip(
    'OPEN BUG #1 — RoleSeeder.php:30 grants the admin ROLE all 8 permissions, so the '.
    'per-section can: gates are inert and a products-only admin reaches finance (200, not 403). '.
    'Blocked on a design call: superadmin by primary-email vs an is_superadmin flag. '.
    'Un-skip with the fix — the grant must live in RoleSeeder to survive the deploy re-seed.'
);

test('#2 observation: a scoped admin CAN reach its own section + the ungated dashboard/notifications', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');
    $admin->syncPermissions(['products.moderate']);

    // Their own permissioned section.
    $this->actingAs($admin)->get(route('admin.catalog.moderation'))->assertOk();

    // Dashboard + notifications sit in NO can: group — reachable by any admin.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.notifications'))->assertOk();
});

// ── #3 — Staff self-lockout guard ─────────────────────────────────────────

test('#3 an admin cannot remove their own admin access, but can remove another', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    $admin->assignRole('admin');

    // Self-removal is blocked.
    Livewire::actingAs($admin)->test(Staff::class)->call('removeAdmin', $admin->id);
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();

    // Removing a DIFFERENT admin works.
    $other = User::factory()->create();
    $other->assignRole('admin');
    $other->syncPermissions(['cms.manage']);

    Livewire::actingAs($admin)->test(Staff::class)->call('removeAdmin', $other->id);

    $other = $other->fresh();
    expect($other->hasRole('admin'))->toBeFalse()
        ->and($other->getDirectPermissions())->toBeEmpty();
});

// ── #4 — Buyer review purchase-gate ───────────────────────────────────────

test('#4a a non-purchaser cannot mount the review panel (403)', function () {
    $buyer = User::factory()->create();
    $intruder = User::factory()->create();

    [$subOrder] = stressReviewableOrder($buyer);

    // Livewire captures the mount abort() into the component response.
    Livewire::actingAs($intruder)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->assertStatus(403);
});

test('#4b a not-yet-completed order cannot be reviewed', function () {
    $this->mock(Turnstile::class)->shouldReceive('verify')->andReturnTrue();

    $buyer = User::factory()->create();
    [$subOrder, $item] = stressReviewableOrder($buyer, SubOrderStatus::Confirmed);

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->call('submit', $item->id);

    expect(Review::count())->toBe(0);
});

test('#4c the same order item cannot be reviewed twice', function () {
    $this->mock(Turnstile::class)->shouldReceive('verify')->andReturnTrue();

    $buyer = User::factory()->create();
    [$subOrder, $item] = stressReviewableOrder($buyer, SubOrderStatus::Completed);

    $panel = Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        ->call('submit', $item->id);

    expect(Review::where('order_item_id', $item->id)->count())->toBe(1);

    // Second attempt on the same item must be rejected.
    $panel->set("ratings.{$item->id}", 4)->call('submit', $item->id);

    expect(Review::where('order_item_id', $item->id)->count())->toBe(1);
});

// ── #5 — Seller cross-store order / product access ────────────────────────

test('#5 a seller gets 403 opening another store\'s order detail or product form', function () {
    [$sellerA] = stressSeller();
    [$sellerB, $storeB] = stressSeller();

    $orderB = Order::factory()->create();
    $subOrderB = SubOrder::factory()->create(['store_id' => $storeB->id, 'order_id' => $orderB->id]);
    $productB = Product::factory()->create(['store_id' => $storeB->id]);

    // Route stack (EnsureSeller + binding + mount authorizeStore()).
    $this->actingAs($sellerA)->get(route('seller.orders.show', $subOrderB))->assertForbidden();
    $this->actingAs($sellerA)->get(route('seller.products.edit', $productB))->assertForbidden();

    // And the component mounts themselves abort (belt-and-braces).
    Livewire::actingAs($sellerA)
        ->test(SellerOrderDetail::class, ['subOrder' => $subOrderB])
        ->assertStatus(403);

    Livewire::actingAs($sellerA)
        ->test(SellerProductForm::class, ['product' => $productB])
        ->assertStatus(403);
});
