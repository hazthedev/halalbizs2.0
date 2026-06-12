<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\SubOrderStatus;
use App\Livewire\Storefront\Account\Profile;
use App\Models\Address;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function m8Buyer(): User
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    return $user;
}

function m8OrderFor(User $user, SubOrderStatus $status = SubOrderStatus::Completed): Order
{
    $order = Order::factory()->create(['user_id' => $user->id]);
    $subOrder = SubOrder::factory()->status($status)->create(['order_id' => $order->id]);

    $subOrder->items()->create([
        'product_name' => 'Sample Snapshot Product',
        'variant_label' => 'Blue / M',
        'unit_price_sen' => 2500,
        'qty' => 2,
        'line_total_sen' => 5000,
    ]);

    return $order;
}

// ── Export my data ──────────────────────────────────────────────────────

test('the data export downloads JSON with profile, addresses and order snapshots', function () {
    $user = m8Buyer();
    Address::factory()->create(['user_id' => $user->id, 'city' => 'Shah Alam']);
    $order = m8OrderFor($user);

    $component = Livewire::actingAs($user)
        ->test(Profile::class)
        ->call('downloadData')
        ->assertFileDownloaded();

    $json = base64_decode((string) data_get($component->effects, 'download.content'));

    expect($json)->toContain($order->order_no)
        ->toContain($user->email)
        ->toContain('Shah Alam')
        ->toContain('Sample Snapshot Product'); // the order_item snapshot, not live data
});

// ── Delete account ──────────────────────────────────────────────────────

test('deletion anonymizes the user, signs them out, and keeps order records', function () {
    $user = m8Buyer();
    m8OrderFor($user); // completed — does not block

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $fresh = User::withTrashed()->find($user->id);

    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->name)->toBe('Deleted user')
        ->and($fresh->email)->toBe('deleted-'.$user->id.'@anonymized.local')
        ->and($fresh->phone)->toBeNull()
        // Orders + snapshots KEPT — legal/financial records (docs/09 §F).
        ->and(Order::where('user_id', $user->id)->count())->toBe(1)
        ->and(auth()->check())->toBeFalse();
});

test('deletion requires typing DELETE and the current password', function () {
    $user = m8Buyer();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'delete') // wrong case — must be exact
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertHasErrors(['delete_confirm']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'wrong-password')
        ->call('deleteAccount')
        ->assertHasErrors(['delete_password']);

    expect(User::withTrashed()->find($user->id)->trashed())->toBeFalse();
});

test('deletion is blocked while orders are still in progress', function () {
    $user = m8Buyer();
    m8OrderFor($user, SubOrderStatus::Processing);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'error');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeFalse();
});

test('deletion is blocked while the seller store holds a non-zero ledger balance', function () {
    $user = m8Buyer();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    $store->ledgerEntries()->create([
        'type' => LedgerEntryType::Sale,
        'amount_sen' => 5000,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Sale',
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'error');

    expect(User::withTrashed()->find($user->id)->trashed())->toBeFalse();
});
