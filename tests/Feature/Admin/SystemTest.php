<?php

use App\Livewire\Admin\System\AuditLog;
use App\Livewire\Admin\System\Settings;
use App\Livewire\Admin\System\Staff;
use App\Models\Store;
use App\Models\User;
use App\Settings\CodSettings;
use App\Settings\ModerationSettings;
use App\Settings\OrderSettings;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function systemAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

// ── Settings ────────────────────────────────────────────────────────────

test('order settings persist including the RM payout minimum as sen', function () {
    Livewire::actingAs(systemAdmin())
        ->test(Settings::class)
        ->set('returnWindowDays', '10')
        ->set('autoCompleteDays', '14')
        ->set('unpaidExpiryMinutes', '90')
        ->set('payoutMin', '100.00')
        ->call('saveOrder')
        ->assertHasNoErrors();

    $settings = app(OrderSettings::class)->refresh();

    expect($settings->return_window_days)->toBe(10)
        ->and($settings->auto_complete_days)->toBe(14)
        ->and($settings->unpaid_expiry_minutes)->toBe(90)
        ->and($settings->payout_min_sen)->toBe(10000);
});

test('cod settings persist with RM-to-sen conversion', function () {
    Livewire::actingAs(systemAdmin())
        ->test(Settings::class)
        ->set('codEnabled', false)
        ->set('codMaxOrder', '750.00')
        ->call('saveCod')
        ->assertHasNoErrors();

    $settings = app(CodSettings::class)->refresh();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->max_order_sen)->toBe(75000);
});

test('moderation settings persist', function () {
    Livewire::actingAs(systemAdmin())
        ->test(Settings::class)
        ->set('requireProductApproval', true)
        ->call('saveModeration')
        ->assertHasNoErrors();

    expect(app(ModerationSettings::class)->refresh()->require_product_approval)->toBeTrue();
});

test('an invalid RM amount blocks the order settings save', function () {
    Livewire::actingAs(systemAdmin())
        ->test(Settings::class)
        ->set('payoutMin', 'not-money')
        ->call('saveOrder')
        ->assertHasErrors(['payoutMin']);
});

// ── Staff & roles ───────────────────────────────────────────────────────

test('inviting a staff member assigns the admin role and the chosen permission subset', function () {
    Livewire::actingAs(systemAdmin())
        ->test(Staff::class)
        ->call('startInvite')
        ->set('inviteName', 'Aisha Rahman')
        ->set('inviteEmail', 'aisha@halalbizs.test')
        ->set('invitePermissions', ['cms.manage', 'vouchers.manage'])
        ->call('invite')
        ->assertHasNoErrors()
        ->assertSet('generatedFor', 'aisha@halalbizs.test');

    $user = User::where('email', 'aisha@halalbizs.test')->sole();

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['cms.manage', 'vouchers.manage']);
});

test('an admin cannot remove their own admin role', function () {
    $admin = systemAdmin();

    Livewire::actingAs($admin)
        ->test(Staff::class)
        ->call('removeAdmin', $admin->id);

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('an admin can remove another admin and their direct permissions', function () {
    $admin = systemAdmin();
    $other = User::factory()->create();
    $other->assignRole('admin');
    $other->syncPermissions(['cms.manage']);

    Livewire::actingAs($admin)
        ->test(Staff::class)
        ->call('removeAdmin', $other->id);

    $other = $other->fresh();

    expect($other->hasRole('admin'))->toBeFalse()
        ->and($other->getDirectPermissions())->toBeEmpty();
});

test('permission edits sync the direct permission subset', function () {
    $admin = systemAdmin();
    $other = User::factory()->create();
    $other->assignRole('admin');
    $other->syncPermissions(['cms.manage']);

    Livewire::actingAs($admin)
        ->test(Staff::class)
        ->call('editPermissions', $other->id)
        ->set('editPermissions', ['orders.manage', 'finance.manage'])
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($other->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(['finance.manage', 'orders.manage']);
});

// ── Audit log ───────────────────────────────────────────────────────────

test('the audit log shows a store activity row with causer and diff data', function () {
    $admin = systemAdmin();
    test()->actingAs($admin);

    $store = Store::factory()->create();
    $store->update(['commission_rate' => '7.50']); // logged: LogsActivity on Store

    Livewire::actingAs($admin)
        ->test(AuditLog::class)
        ->assertSee('Store')
        ->assertSee($admin->name)
        ->set('subjectType', 'Store')
        ->assertSee('Store')
        ->set('subjectType', 'Payout')
        ->assertDontSee('#'.$store->id);

    expect(Activity::where('subject_type', Store::class)->exists())->toBeTrue();
});

// ── Access control ──────────────────────────────────────────────────────

test('non-admins get 403 on every system route', function () {
    test()->seed(RoleSeeder::class);
    $buyer = User::factory()->create();

    foreach (['admin.system.settings', 'admin.system.staff', 'admin.system.audit'] as $route) {
        test()->actingAs($buyer)->get(route($route))->assertForbidden();
    }
});

test('admins can open every system screen', function () {
    test()->seed(CurrencySeeder::class);
    $admin = systemAdmin();

    foreach (['admin.system.settings', 'admin.system.staff', 'admin.system.audit'] as $route) {
        test()->actingAs($admin)->get(route($route))->assertOk();
    }
});
