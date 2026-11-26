<?php

use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Sellers\StoreDetail;
use App\Livewire\Admin\Support\Tickets as AdminTickets;
use App\Livewire\Storefront\Account\Profile;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AccountDeletionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// HB-DEL3: follow-ups to HB-DEL2 (#282).

/** A seller with an approved store, one live product and no ledger entries (zero balance). */
function hbDel3Seller(): array
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['store_id' => $store->id]);

    return [$user, $store, $product];
}

/** What a deletion did before #282: anonymise + soft delete, nothing erased. */
function hbDel3DeleteTheOldWay(User $user): void
{
    $user->forceFill(['name' => 'Deleted user', 'email' => 'deleted-'.$user->id.'@anonymized.local', 'phone' => null])->save();
    $user->delete();
}

// (6) shop closes

test('deleting a zero-balance seller closes the shop: off the storefront, live catalogue and API, records kept', function () {
    [$user, $store, $product] = hbDel3Seller();

    expect(Product::live()->whereKey($product->id)->exists())->toBeTrue();
    $this->get(route('store.show', $store))->assertOk();

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull();

    $store->refresh();
    expect($store->status)->toBe(StoreStatus::Suspended)
        ->and($store->rejection_reason)->toBe('Owner account deleted.')
        ->and(Product::live()->whereKey($product->id)->exists())->toBeFalse() // search + listing + cart read live()
        ->and(Product::whereKey($product->id)->exists())->toBeTrue(); // the record stays

    $this->get(route('store.show', $store))->assertNotFound();
    $this->getJson(route('api.stores.show', $store))->assertNotFound();
    $this->getJson(route('api.products'))->assertOk()->assertDontSee($product->slug);
});

test('an admin suspension keeps its own reason when the owner deletes the account', function () {
    [$user, $store] = hbDel3Seller();
    $store->forceFill(['status' => StoreStatus::Suspended, 'rejection_reason' => 'Selling non-halal goods as halal'])->save();

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull()
        ->and($store->refresh()->rejection_reason)->toBe('Selling non-halal goods as halal');
});

test('a store reinstated by mistake can be suspended again although its owner is deleted', function () {
    [$user, $store] = hbDel3Seller();
    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull();

    Livewire::actingAs(hbDel3Admin())->test(StoreDetail::class, ['store' => $store->refresh()])
        ->call('reinstate')
        ->set('suspendReason', 'Owner account deleted.')
        ->call('suspend')
        ->assertHasNoErrors();

    expect($store->refresh()->status)->toBe(StoreStatus::Suspended);
});

test('deleting from the profile page leaves no sign-in column behind (logout must not write a new remember token)', function () {
    $user = User::factory()->create(['remember_token' => 'remember-me-token']);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('delete_confirm', 'DELETE')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertHasNoErrors();

    expect(User::withTrashed()->find($user->id)->remember_token)->toBeNull();
});

// (7) backfill command

test('accounts:erase-deleted dry run changes nothing; the real run erases old deletions only, and a rerun finds nothing', function () {
    $old = User::factory()->create();
    Address::factory()->count(2)->create(['user_id' => $old->id]);
    $old->forceFill(['google_id' => 'g-old'])->save();
    hbDel3DeleteTheOldWay($old);

    [$oldSeller, $oldStore] = hbDel3Seller();
    hbDel3DeleteTheOldWay($oldSeller);

    // Must never match: a live account, a live account that picked a look-alike
    // email, and a soft-deleted account that was never anonymised.
    $live = User::factory()->create();
    Address::factory()->create(['user_id' => $live->id]);
    $lookAlike = User::factory()->create();
    $lookAlike->forceFill(['email' => 'deleted-'.$lookAlike->id.'@anonymized.local'])->save();
    Address::factory()->create(['user_id' => $lookAlike->id]);
    $trashed = User::factory()->create();
    Address::factory()->create(['user_id' => $trashed->id]);
    $trashed->delete();

    $this->artisan('accounts:erase-deleted', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN, nothing changed. Deleted accounts matched: 2')
        ->doesntExpectOutputToContain('@')
        ->assertSuccessful();

    expect(Address::where('user_id', $old->id)->count())->toBe(2)
        ->and(User::withTrashed()->find($old->id)->google_id)->toBe('g-old')
        ->and($oldStore->refresh()->status)->toBe(StoreStatus::Approved);

    $this->artisan('accounts:erase-deleted')
        ->expectsOutputToContain('Deleted accounts matched: 2')
        ->expectsOutputToContain('Skipped by design: password_reset_tokens')
        ->assertSuccessful();

    expect(Address::where('user_id', $old->id)->count())->toBe(0)
        ->and(User::withTrashed()->find($old->id)->google_id)->toBeNull()
        ->and($oldStore->refresh()->status)->toBe(StoreStatus::Suspended)
        ->and(Address::whereIn('user_id', [$live->id, $lookAlike->id, $trashed->id])->count())->toBe(3);

    // Idempotent: a rerun has nothing left to erase.
    $deletion = app(AccountDeletionService::class);
    foreach ([$old, $oldSeller] as $user) {
        expect(array_sum($deletion->erase(User::withTrashed()->find($user->id), null, dryRun: true)))->toBe(0);
    }
    $this->artisan('accounts:erase-deleted')->assertSuccessful();
    expect(Address::whereIn('user_id', [$live->id, $lookAlike->id, $trashed->id])->count())->toBe(3);
});

// (8) "Deleted user" on admin + seller pages

function hbDel3DeletedBuyerSubOrder(): SubOrder
{
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    [, $store] = hbDel3Seller();
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id, 'store_id' => $store->id]);

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($buyer))->toBeNull();

    return $subOrder;
}

function hbDel3Admin(): User
{
    return makeAdmin(User::factory()->create(['two_factor_method' => 'email'])); // admins need 2FA (EnsureAdmin)
}

test('admin order list and detail show "Deleted user" for a deleted buyer', function () {
    $subOrder = hbDel3DeletedBuyerSubOrder();
    $admin = hbDel3Admin();

    $this->actingAs($admin)->get(route('admin.orders.show', $subOrder))->assertOk()->assertSee('Buyer: Deleted user');
    $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSee($subOrder->sub_order_no)->assertSee('Deleted user');
});

test('seller order list and detail show "Deleted user" for a deleted buyer', function () {
    $subOrder = hbDel3DeletedBuyerSubOrder();
    $seller = $subOrder->store->user;

    $this->actingAs($seller)->get(route('seller.orders.show', $subOrder))->assertOk()->assertSee('Buyer: Deleted user');
    $this->actingAs($seller)->get(route('seller.orders.index', ['tab' => 'completed']))->assertOk()->assertSee($subOrder->sub_order_no)->assertSee('Deleted user');
});

test('admin ticket queue and thread show "Deleted user" for a deleted buyer, and a reply still sends', function () {
    $buyer = User::factory()->create();
    $ticket = $buyer->tickets()->create(['subject' => 'Parcel missing', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);
    $ticket->replies()->create(['author_type' => 'user', 'author_id' => $buyer->id, 'body' => 'Where is my parcel?']);
    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($buyer))->toBeNull();
    $admin = hbDel3Admin();

    $this->actingAs($admin)->get(route('admin.support.tickets'))->assertOk()->assertSee('Deleted user');

    Livewire::actingAs($admin)->test(AdminTickets::class)
        ->call('select', $ticket->id)
        ->assertSee('Deleted user')
        ->set('replyBody', 'We are checking with the courier.')
        ->call('reply')
        ->assertHasNoErrors();

    expect(DB::table('ticket_replies')->where('ticket_id', $ticket->id)->count())->toBe(2);
});

test('"Deleted user" is translated in ms and vi', function () {
    foreach (['ms', 'vi'] as $locale) {
        app()->setLocale($locale);
        expect(__('Deleted user'))->not->toBe('Deleted user');
    }
});
