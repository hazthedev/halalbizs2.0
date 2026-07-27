<?php

/**
 * Admin least-privilege (bug #1) and the superadmin that makes it survivable.
 *
 * The `admin` role carries no permissions — it only gets you past EnsureAdmin.
 * Sections are gated per-person, and a superadmin passes everything via
 * Gate::before so the Staff screen stays reachable after the role was emptied.
 */

use App\Livewire\Admin\System\Staff;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

function scopedAdmin(array $permissions = []): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions($permissions);

    return $user->fresh();
}

function superadmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->forceFill(['is_superadmin' => true])->save();

    return $user->fresh();
}

// ── The role itself ────────────────────────────────────────────────────────

test('the admin role grants no permissions — it only opens the door', function () {
    $role = Role::findByName('admin', 'web');

    expect($role->permissions)->toBeEmpty();
});

test('an admin with no grants reaches the dashboard but no gated section', function () {
    $admin = scopedAdmin();

    test()->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    test()->actingAs($admin)->get(route('admin.finance.payouts'))->assertForbidden();
    test()->actingAs($admin)->get(route('admin.system.staff'))->assertForbidden();
});

test('a scoped admin reaches only its own section', function () {
    $admin = scopedAdmin(['products.moderate']);

    test()->actingAs($admin)->get(route('admin.catalog.moderation'))->assertOk();
    test()->actingAs($admin)->get(route('admin.finance.payouts'))->assertForbidden();
});

// ── The superadmin bypass ──────────────────────────────────────────────────

test('a superadmin passes every section without holding a single permission', function () {
    $super = superadmin();

    expect($super->getDirectPermissions())->toBeEmpty();

    foreach ([
        'admin.finance.payouts',
        'admin.system.staff',
        'admin.catalog.moderation',
        'admin.localization',
    ] as $route) {
        test()->actingAs($super)->get(route($route))->assertOk();
    }
});

test('the superadmin bypass does not leak to ordinary admins', function () {
    expect(scopedAdmin()->can('finance.manage'))->toBeFalse()
        ->and(superadmin()->can('finance.manage'))->toBeTrue();
});

// ── Granting it from the panel ─────────────────────────────────────────────

test('a superadmin can promote and demote another admin from the panel', function () {
    $super = superadmin();
    $target = scopedAdmin(['products.moderate']);

    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $target->id);
    expect($target->fresh()->is_superadmin)->toBeTrue();

    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $target->id);
    expect($target->fresh()->is_superadmin)->toBeFalse();
});

test('a non-superadmin cannot grant superadmin, not even to themselves', function () {
    // settings.manage is what opens the Staff screen — the most privileged
    // ordinary admin there is. It still must not be able to self-promote.
    $admin = scopedAdmin(['settings.manage']);

    Livewire::actingAs($admin)->test(Staff::class)->call('toggleSuperadmin', $admin->id);

    expect($admin->fresh()->is_superadmin)->toBeFalse();
});

// ── Lockout guards ─────────────────────────────────────────────────────────

test('the last superadmin cannot demote themselves', function () {
    $super = superadmin();

    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $super->id);

    expect($super->fresh()->is_superadmin)->toBeTrue();
});

test('the last superadmin cannot have their admin role removed either', function () {
    // Same lockout by another door: removeAdmin() would strip the role and the
    // flag, leaving nobody able to grant permissions.
    $super = superadmin();
    $other = superadmin();

    Livewire::actingAs($other)->test(Staff::class)->call('toggleSuperadmin', $super->id);
    expect($super->fresh()->is_superadmin)->toBeFalse();

    // $other is now the only one left — removing them must be refused.
    $mover = scopedAdmin(['settings.manage']);
    Livewire::actingAs($mover)->test(Staff::class)->call('removeAdmin', $other->id);

    expect($other->fresh()->hasRole('admin'))->toBeTrue()
        ->and($other->fresh()->is_superadmin)->toBeTrue();
});

// ── Dashboard money is gated in the view, not the route ────────────────────

test('the dashboard hides platform revenue from an admin without finance.manage', function () {
    // The route stays open — it is every admin's landing page — so this gating
    // is invisible to route-level tests, which is exactly why it gets its own.
    //
    // Asserts on the whole response body on purpose: the numbers reach the
    // browser by THREE independent channels — rendered markup, the chart
    // component's Livewire snapshot, and a dispatch() browser event — and
    // gating only the markup leaves the other two wide open. Each of the
    // strings below caught a different one of those during development.
    $response = test()->actingAs(scopedAdmin(['products.moderate']))
        ->get(route('admin.dashboard'))
        ->assertOk();

    $response->assertDontSee('GMV (paid)')          // tile AND chart series name (snapshot + dispatch)
        ->assertDontSee('Commission revenue')
        ->assertDontSee('Boost revenue')
        ->assertDontSee('GMV — last 30 days', escape: false)
        ->assertDontSee('Top stores by GMV')
        ->assertDontSee('Payout requests')          // pending-queue tile leaks a finance count
        ->assertDontSee('admin/finance/payouts')    // and the sidebar advertised the section
        ->assertDontSee('admin/finance/commission')
        // ...while the operational tiles every admin needs stay put.
        ->assertSee('Orders today');
});

test('the sidebar only advertises sections the admin can actually open', function () {
    $response = test()->actingAs(scopedAdmin(['products.moderate']))
        ->get(route('admin.dashboard'))
        ->assertOk();

    $response->assertSee('admin/catalog/moderation')  // granted
        ->assertDontSee('admin/system/staff')         // not granted
        ->assertDontSee('admin/sellers/applications');
});

test('the dashboard shows platform revenue to finance.manage and to a superadmin', function () {
    foreach ([scopedAdmin(['finance.manage']), superadmin()] as $user) {
        test()->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('GMV (paid)')
            ->assertSee('Commission revenue')
            ->assertSee('Top stores by GMV');
    }
});

// ── The deploy hazard ──────────────────────────────────────────────────────

test('re-running RoleSeeder keeps the role empty', function () {
    // deploy.sh re-seeds RoleSeeder on every deploy. If the fix did not live in
    // the seeder, each deploy would silently re-grant all eight permissions.
    Role::findByName('admin', 'web')->syncPermissions(RoleSeeder::ADMIN_PERMISSIONS);

    test()->seed(RoleSeeder::class);

    expect(Role::findByName('admin', 'web')->permissions)->toBeEmpty();
});

test('a superadmin survives the deploy re-seed', function () {
    $super = superadmin();

    test()->seed(RoleSeeder::class);

    expect($super->fresh()->is_superadmin)->toBeTrue()
        ->and($super->fresh()->can('finance.manage'))->toBeTrue();
});
