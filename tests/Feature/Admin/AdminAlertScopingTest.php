<?php

/**
 * AL-C3: admin alerts (AdminAlertObserver) must respect the same per-person
 * permission gate as the admin route they link to. Before this fix every
 * admin-role user got every alert regardless of section access, so a
 * CMS-only admin could read payout ringgit amounts in their notification
 * bell despite being 403'd from admin.finance.payouts.
 *
 * Also covers M8: the Staff screen must not let an admin grant themselves
 * a permission they don't already hold.
 */

use App\Enums\PayoutStatus;
use App\Livewire\Admin\System\Staff;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Support\Money;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

function alertScopedAdmin(array $permissions = []): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->syncPermissions($permissions);

    return $user->fresh();
}

function alertSuperadmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    $user->assignRole('admin');
    $user->forceFill(['is_superadmin' => true])->save();

    return $user->fresh();
}

function alertStoreForPayout(): Store
{
    $owner = User::factory()->create();
    $owner->assignRole('seller');

    return Store::factory()->approved()->create(['user_id' => $owner->id]);
}

function requestAlertPayout(Store $store): Payout
{
    return Payout::create([
        'payout_no' => Payout::generatePayoutNo(),
        'store_id' => $store->id,
        'amount_sen' => 125000,
        'status' => PayoutStatus::Requested,
        'bank_snapshot' => $store->bank_details,
        'requested_at' => now(),
    ]);
}

test('a payout alert is withheld from an admin without finance.manage', function () {
    Notification::fake();

    $admin = alertScopedAdmin(['products.moderate']); // a real section, just not finance
    requestAlertPayout(alertStoreForPayout());

    Notification::assertNotSentTo($admin, AdminAlertNotification::class);
});

test('a payout alert reaches an admin who holds finance.manage', function () {
    Notification::fake();

    $admin = alertScopedAdmin(['finance.manage']);
    requestAlertPayout(alertStoreForPayout());

    Notification::assertSentTo(
        $admin,
        AdminAlertNotification::class,
        fn ($notification) => str_contains($notification->message, Money::format(125000))
            && $notification->url === route('admin.finance.payouts'),
    );
});

test('a superadmin receives the payout alert despite holding no direct permissions', function () {
    Notification::fake();

    $super = alertSuperadmin();
    expect($super->getDirectPermissions())->toBeEmpty();

    requestAlertPayout(alertStoreForPayout());

    Notification::assertSentTo($super, AdminAlertNotification::class);
});

test('an admin with no grants at all receives nothing when a payout is requested', function () {
    Notification::fake();

    $admin = alertScopedAdmin(); // bare `admin` role, zero permissions
    requestAlertPayout(alertStoreForPayout());

    Notification::assertNotSentTo($admin, AdminAlertNotification::class);
});

// ── M8: no self-grant ───────────────────────────────────────────────────

test('an admin cannot grant permissions to their own account', function () {
    $admin = alertScopedAdmin(['settings.manage']); // the role that opens the Staff screen

    Livewire::actingAs($admin)
        ->test(Staff::class)
        ->call('editPermissions', $admin->id)
        ->assertSet('editingId', null); // refused entry into edit mode for self

    expect($admin->fresh()->getDirectPermissions()->pluck('name')->all())
        ->toBe(['settings.manage']);
});
