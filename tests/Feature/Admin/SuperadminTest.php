<?php

/**
 * Admin least-privilege (bug #1) and the superadmin that makes it survivable.
 *
 * The `admin` role carries no permissions — it only gets you past EnsureAdmin.
 * Sections are gated per-person, and a superadmin passes everything via
 * Gate::before so the Staff screen stays reachable after the role was emptied.
 */

use App\Livewire\Admin\System\AuditLog;
use App\Livewire\Admin\System\Staff;
use App\Livewire\Storefront\Account\Profile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
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

test('a non-superadmin cannot remove a superadmin (HB-DEC-1123d)', function () {
    // Same rule as promoting one: only a superadmin may take superadmin
    // access away, even when more than one superadmin exists.
    superadmin();
    $target = superadmin();
    $admin = scopedAdmin(['settings.manage']);

    Livewire::actingAs($admin)->test(Staff::class)->call('removeAdmin', $target->id);

    expect($target->fresh()->hasRole('admin'))->toBeTrue()
        ->and($target->fresh()->is_superadmin)->toBeTrue();
});

test('a superadmin can remove another superadmin while one remains', function () {
    $super = superadmin();
    $target = superadmin();

    Livewire::actingAs($super)->test(Staff::class)->call('removeAdmin', $target->id);

    expect($target->fresh()->hasRole('admin'))->toBeFalse()
        ->and($target->fresh()->is_superadmin)->toBeFalse()
        ->and($super->fresh()->is_superadmin)->toBeTrue();
});

// ── Staff audit trail (HB-G036) ────────────────────────────────────────────

test('every staff action writes an audit line with causer, subject and old/new access', function () {
    $super = superadmin();

    Livewire::actingAs($super)->test(Staff::class)
        ->set('inviteName', 'New Staff')
        ->set('inviteEmail', 'new-staff@example.com')
        ->set('invitePermissions', ['cms.manage'])
        ->call('invite');
    $staff = User::where('email', 'new-staff@example.com')->sole();

    Livewire::actingAs($super)->test(Staff::class)
        ->call('startEditPermissions', $staff->id)
        ->set('editPermissions', ['cms.manage', 'products.moderate'])
        ->call('savePermissions');
    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $staff->id);
    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $staff->id);
    Livewire::actingAs($super)->test(Staff::class)->call('removeAdmin', $staff->id);

    $lines = Activity::query()->where('subject_type', $staff->getMorphClass())
        ->where('subject_id', $staff->id)->orderBy('id')->get();

    expect($lines->pluck('description')->all())->toBe([
        'admin invited',
        'admin permissions changed',
        'superadmin granted',
        'superadmin removed',
        'admin removed',
    ])
        ->and($lines->pluck('causer_id')->unique()->all())->toBe([$super->id])
        ->and($lines[0]->properties['attributes'])->toBe(['permissions' => ['cms.manage']])
        ->and($lines[1]->properties['old'])->toBe(['permissions' => ['cms.manage']])
        ->and($lines[1]->properties['attributes'])->toBe(['permissions' => ['cms.manage', 'products.moderate']])
        ->and($lines[2]->properties->all())->toBe(['old' => ['is_superadmin' => false], 'attributes' => ['is_superadmin' => true]])
        ->and($lines[3]->properties->all())->toBe(['old' => ['is_superadmin' => true], 'attributes' => ['is_superadmin' => false]])
        ->and($lines[4]->properties['old'])->toBe(['permissions' => ['cms.manage', 'products.moderate'], 'is_superadmin' => false]);
});

test('staff audit lines render in the audit log screen with their old to new diff', function () {
    $super = superadmin();
    $staff = scopedAdmin(['cms.manage']);

    Livewire::actingAs($super)->test(Staff::class)
        ->call('startEditPermissions', $staff->id)
        ->set('editPermissions', ['cms.manage', 'products.moderate'])
        ->call('savePermissions');
    Livewire::actingAs($super)->test(Staff::class)->call('toggleSuperadmin', $staff->id);

    Livewire::actingAs($super)->test(AuditLog::class)
        ->assertOk()
        ->assertSee('superadmin granted')
        ->assertSee('is_superadmin:')
        ->assertSee('permissions:')
        ->assertSee('["cms.manage","products.moderate"]');
});

test('a refused staff action writes no audit line', function () {
    $target = superadmin();
    superadmin();
    $admin = scopedAdmin(['settings.manage']);

    Livewire::actingAs($admin)->test(Staff::class)->call('removeAdmin', $target->id);
    Livewire::actingAs($admin)->test(Staff::class)->call('toggleSuperadmin', $target->id);

    expect(Activity::query()->where('subject_id', $target->id)->exists())->toBeFalse();
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

// ── HB-B5A security follow-ups ─────────────────────────────────────────────

/** SQL run by $action, lower-cased. */
function b5aQueries(Closure $action): array
{
    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    $action();

    return $sql;
}

test('HB-G234: the keep-one superadmin check reads the superadmin rows under a row lock', function (string $method) {
    $super = superadmin();
    $target = superadmin();

    // Two removals at the same moment each saw "2 left" and both went
    // through. The count must be a locking read, so the second one waits for
    // the first and then sees 1. A single-process test cannot race; it proves
    // the locking read is what the guard runs.
    $sql = b5aQueries(fn () => Livewire::actingAs($super)->test(Staff::class)->call($method, $target->id));

    expect(collect($sql)->contains(fn ($q) => str_contains($q, 'is_superadmin') && str_ends_with($q, 'for update')))->toBeTrue()
        ->and($target->fresh()->is_superadmin)->toBeFalse();
})->with(['toggleSuperadmin', 'removeAdmin']);

test('HB-G236: an admin cannot remove an admin holding a permission they lack (HB-DEC-1124f 9)', function () {
    $mover = scopedAdmin(['settings.manage']);
    $target = scopedAdmin(['settings.manage', 'finance.manage']);

    Livewire::actingAs($mover)->test(Staff::class)->call('removeAdmin', $target->id);

    expect($target->fresh()->hasRole('admin'))->toBeTrue()
        ->and($target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'settings.manage']);
});

test('HB-G236: an admin holding every permission of the other admin can remove them', function () {
    $mover = scopedAdmin(['settings.manage', 'finance.manage']);
    $target = scopedAdmin(['finance.manage']);

    Livewire::actingAs($mover)->test(Staff::class)->call('removeAdmin', $target->id);

    expect($target->fresh()->hasRole('admin'))->toBeFalse();
});

test('HB-G236: an admin cannot strip permissions from an admin holding one they lack', function () {
    $editor = scopedAdmin(['settings.manage']);
    $target = scopedAdmin(['settings.manage', 'finance.manage']);

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['settings.manage'])
        ->call('savePermissions')
        ->assertHasErrors('editPermissions');

    expect($target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'settings.manage']);
});

test('HB-G236 relax: an admin may ADD a permission they hold to an admin holding one they lack (HB-DEC-1125b)', function () {
    $editor = scopedAdmin(['settings.manage', 'orders.manage']);
    $target = scopedAdmin(['settings.manage', 'finance.manage']);

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['settings.manage', 'finance.manage', 'orders.manage'])
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'orders.manage', 'settings.manage']);
});

test('HB-G236 relax: adding one permission while removing another still needs every permission the target holds', function () {
    $editor = scopedAdmin(['settings.manage', 'orders.manage']);
    $target = scopedAdmin(['settings.manage', 'finance.manage']);

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['finance.manage', 'orders.manage'])
        ->call('savePermissions')
        ->assertHasErrors('editPermissions');

    expect($target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'settings.manage']);
});

test('HB-G236 relax: an admin still cannot add a permission they do not hold', function () {
    $editor = scopedAdmin(['settings.manage']);
    $target = scopedAdmin(['settings.manage', 'finance.manage']);

    Livewire::actingAs($editor)->test(Staff::class)
        ->call('startEditPermissions', $target->id)
        ->set('editPermissions', ['settings.manage', 'finance.manage', 'orders.manage'])
        ->call('savePermissions')
        ->assertHasErrors('editPermissions');

    expect($target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'settings.manage']);
});

test('HB-G237: the last superadmin cannot delete their own account (web)', function () {
    $super = superadmin();

    Livewire::actingAs($super)->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertNoRedirect();

    expect(User::withTrashed()->find($super->id)->trashed())->toBeFalse();
});

test('HB-G237: the last superadmin cannot delete their own account (app API)', function () {
    $super = superadmin();

    test()->withToken($super->createToken('pest-phone')->plainTextToken)
        ->deleteJson('/api/v1/me', ['password' => 'password'])
        ->assertStatus(409);

    expect(User::withTrashed()->find($super->id)->trashed())->toBeFalse();
});

test('HB-G237: a superadmin can delete their account while another superadmin remains', function () {
    $super = superadmin();
    superadmin();

    Livewire::actingAs($super)->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertRedirect(route('home'));

    expect(User::withTrashed()->find($super->id)->trashed())->toBeTrue();
});
