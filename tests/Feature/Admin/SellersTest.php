<?php

use App\Enums\DocumentStatus;
use App\Enums\StoreStatus;
use App\Livewire\Admin\Sellers\Applications;
use App\Livewire\Admin\Sellers\StoreDetail;
use App\Livewire\Admin\Sellers\Stores;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\User;
use App\Notifications\SellerApplicationDecision;
use App\Notifications\StoreSuspended;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function sellersAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

test('guests are redirected and non-admins get 403 on admin seller routes', function () {
    $this->get(route('admin.sellers.applications'))->assertRedirect(route('login'));

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route('admin.sellers.applications'))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.sellers.stores'))->assertForbidden();

    $store = Store::factory()->approved()->create();
    $this->actingAs($buyer)->get(route('admin.sellers.stores.show', $store))->assertForbidden();
});

test('an admin can open the applications queue and the stores list', function () {
    $admin = sellersAdmin();

    $this->actingAs($admin)->get(route('admin.sellers.applications'))->assertOk();
    $this->actingAs($admin)->get(route('admin.sellers.stores'))->assertOk();
});

test('the applications queue lists pending stores only', function () {
    Store::factory()->create(['name' => 'Pending Mart']);
    Store::factory()->approved()->create(['name' => 'Approved Mart']);
    Store::factory()->create(['name' => 'Rejected Mart', 'status' => StoreStatus::Rejected]);

    Livewire::actingAs(sellersAdmin())
        ->test(Applications::class)
        ->assertSee('Pending Mart')
        ->assertDontSee('Approved Mart')
        ->assertDontSee('Rejected Mart');
});

test('approving an application approves the store, grants the seller role and notifies the owner', function () {
    Notification::fake();

    $store = Store::factory()->create();

    Livewire::actingAs(sellersAdmin())
        ->test(Applications::class)
        ->call('approve', $store->id)
        ->assertHasNoErrors();

    $store->refresh();

    expect($store->status)->toBe(StoreStatus::Approved)
        ->and($store->approved_at)->not->toBeNull()
        ->and($store->rejection_reason)->toBeNull()
        ->and($store->user->fresh()->hasRole('seller'))->toBeTrue();

    Notification::assertSentTo(
        $store->user,
        SellerApplicationDecision::class,
        fn (SellerApplicationDecision $notification) => $notification->decision === 'approved',
    );
});

test('rejecting an application requires a reason and stores it', function () {
    Notification::fake();

    $store = Store::factory()->create();

    $component = Livewire::actingAs(sellersAdmin())
        ->test(Applications::class)
        ->call('reject', $store->id)
        ->assertHasErrors(['rejectionReason' => 'required']);

    expect($store->fresh()->status)->toBe(StoreStatus::Pending);
    Notification::assertNothingSent();

    $component
        ->set('rejectionReason', 'SSM certificate was unreadable.')
        ->call('reject', $store->id)
        ->assertHasNoErrors();

    $store->refresh();

    expect($store->status)->toBe(StoreStatus::Rejected)
        ->and($store->rejection_reason)->toBe('SSM certificate was unreadable.')
        ->and($store->user->fresh()->hasRole('seller'))->toBeFalse();

    Notification::assertSentTo(
        $store->user,
        SellerApplicationDecision::class,
        fn (SellerApplicationDecision $notification) => $notification->decision === 'rejected'
            && $notification->reason === 'SSM certificate was unreadable.',
    );
});

test('application documents can be verified and rejected with notes', function () {
    $store = Store::factory()->create();
    $ssm = StoreDocument::create(['store_id' => $store->id, 'type' => 'ssm', 'status' => DocumentStatus::Pending]);
    $ic = StoreDocument::create(['store_id' => $store->id, 'type' => 'ic', 'status' => DocumentStatus::Pending]);

    Livewire::actingAs(sellersAdmin())
        ->test(Applications::class)
        ->call('verifyDocument', $ssm->id)
        ->set('docNotes.'.$ic->id, 'Too blurry to read.')
        ->call('rejectDocument', $ic->id);

    expect($ssm->fresh()->status)->toBe(DocumentStatus::Verified)
        ->and($ic->fresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($ic->fresh()->notes)->toBe('Too blurry to read.');
});

test('the stores list shows decided stores, hides pending ones and searches by owner email', function () {
    $approved = Store::factory()->approved()->create(['name' => 'Approved Mart']);
    Store::factory()->approved()->create(['name' => 'Another Shop']);
    Store::factory()->create(['name' => 'Pending Mart']);

    Livewire::actingAs(sellersAdmin())
        ->test(Stores::class)
        ->assertSee('Approved Mart')
        ->assertSee('Another Shop')
        ->assertDontSee('Pending Mart')
        ->set('search', $approved->user->email)
        ->assertSee('Approved Mart')
        ->assertDontSee('Another Shop');
});

test('a store can be suspended with a reason and reinstated', function () {
    Notification::fake();

    $store = Store::factory()->approved()->create();

    $component = Livewire::actingAs(sellersAdmin())
        ->test(StoreDetail::class, ['store' => $store])
        ->call('suspend')
        ->assertHasErrors(['suspendReason' => 'required']);

    expect($store->fresh()->status)->toBe(StoreStatus::Approved);

    $component
        ->set('suspendReason', 'Repeated counterfeit listings.')
        ->call('suspend')
        ->assertHasNoErrors();

    $store->refresh();

    expect($store->status)->toBe(StoreStatus::Suspended)
        ->and($store->rejection_reason)->toBe('Repeated counterfeit listings.');

    Notification::assertSentTo($store->user, StoreSuspended::class);

    $component->call('reinstate');

    $store->refresh();

    expect($store->status)->toBe(StoreStatus::Approved)
        ->and($store->rejection_reason)->toBeNull();
});

test('the commission override saves, clears and validates the range', function () {
    $store = Store::factory()->approved()->create();

    $component = Livewire::actingAs(sellersAdmin())
        ->test(StoreDetail::class, ['store' => $store])
        ->set('commissionRate', '7.5')
        ->call('saveCommission')
        ->assertHasNoErrors();

    expect($store->fresh()->commission_rate)->toBe('7.50');

    $component->set('commissionRate', '150')
        ->call('saveCommission')
        ->assertHasErrors(['commissionRate' => 'max']);

    expect($store->fresh()->commission_rate)->toBe('7.50');

    $component->set('commissionRate', '')
        ->call('saveCommission')
        ->assertHasNoErrors();

    expect($store->fresh()->commission_rate)->toBeNull();
});
