<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Livewire\Admin\Finance\Payouts;
use App\Livewire\Seller\Earnings;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/**
 * Approved seller whose store ledger holds +RM205.00 sale −RM10.00
 * commission = RM195.00 available (mirrors the LedgerServiceTest shape).
 */
function m8SellerStore(): Store
{
    $user = User::factory()->create();
    $user->assignRole('seller');

    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    $store->ledgerEntries()->create([
        'type' => LedgerEntryType::Sale,
        'amount_sen' => 20500,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Sale SO2606AAAAAA',
    ]);

    $store->ledgerEntries()->create([
        'type' => LedgerEntryType::Commission,
        'amount_sen' => -1000,
        'status' => LedgerEntryStatus::Available,
        'description' => 'Commission 5% on SO2606AAAAAA',
    ]);

    return $store;
}

test('the earnings page shows the ledger balance, entries and signed amounts', function () {
    $store = m8SellerStore();

    $this->actingAs($store->user)->get(route('seller.earnings'))->assertOk();

    Livewire::actingAs($store->user)
        ->test(Earnings::class)
        ->assertSee('RM 195.00')          // available = 20500 − 1000
        ->assertSee('Sale SO2606AAAAAA')  // ledger rows
        ->assertSee('+RM 205.00')         // credits signed +
        ->assertSee('-RM 10.00');         // debits signed −
});

test('a negative balance gets danger styling context — the COD explainer', function () {
    $user = User::factory()->create();
    $user->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $user->id]);

    $store->ledgerEntries()->create([
        'type' => LedgerEntryType::CodOffset,
        'amount_sen' => -1000,
        'status' => LedgerEntryStatus::Available,
        'description' => 'COD cash collected',
    ]);

    Livewire::actingAs($user)
        ->test(Earnings::class)
        ->assertSee('-RM 10.00')
        ->assertSee('COD commission owed');
});

test('requesting a payout via the UI earmarks the amount and shows it pending', function () {
    $store = m8SellerStore(); // RM195.00 available

    Livewire::actingAs($store->user)
        ->test(Earnings::class)
        ->set('amount', '150.00')
        ->call('requestPayout')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $payout = $store->payouts()->sole();

    expect($payout->status)->toBe(PayoutStatus::Requested)
        ->and($payout->amount_sen)->toBe(15000)
        ->and($payout->bank_snapshot)->not->toBeNull()
        ->and($store->availableBalanceSen())->toBe(4500); // earmarked via the negative entry
});

test('below-minimum and above-available requests error-toast and create nothing', function () {
    $store = m8SellerStore();

    $component = Livewire::actingAs($store->user)->test(Earnings::class);

    // Below the RM50 default minimum.
    $component->set('amount', '10.00')
        ->call('requestPayout')
        ->assertDispatched('toast', type: 'error');

    // Above the RM195.00 available balance.
    $component->set('amount', '999.00')
        ->call('requestPayout')
        ->assertDispatched('toast', type: 'error');

    // Unparseable input never reaches the service.
    $component->set('amount', 'not-money')
        ->call('requestPayout')
        ->assertHasErrors(['amount']);

    expect($store->payouts()->count())->toBe(0)
        ->and($store->availableBalanceSen())->toBe(19500);
});

test('payout history lists payout number, status and reference', function () {
    $store = m8SellerStore();

    $paid = $store->payouts()->create([
        'payout_no' => Payout::generatePayoutNo(),
        'amount_sen' => 12500,
        'status' => PayoutStatus::Paid,
        'bank_snapshot' => $store->bank_details,
        'requested_at' => now()->subDays(3),
        'paid_at' => now(),
        'reference' => 'MBB-20260612-001',
    ]);

    Livewire::actingAs($store->user)
        ->test(Earnings::class)
        ->assertSee($paid->payout_no)
        ->assertSee('MBB-20260612-001')
        ->assertSee('RM 125.00'); // also the paid-out lifetime card
});

test('admin rejection releases the earmark back to the available balance', function () {
    $store = m8SellerStore();

    $payout = app(LedgerService::class)->requestPayout($store, 15000);
    expect($store->availableBalanceSen())->toBe(4500);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->call('openReject', $payout->id)
        ->set('rejectReason', 'Bank details do not match the verified documents')
        ->call('reject')
        ->assertHasNoErrors();

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->reference)->toBe('Bank details do not match the verified documents')
        ->and($payout->processed_by)->toBe($admin->id)
        ->and($payout->ledgerEntries()->count())->toBe(0) // earmark entry deleted
        ->and($store->availableBalanceSen())->toBe(19500); // balance restored
});

test('mark paid leaves the ledger untouched — the request-time debit already settled it', function () {
    $store = m8SellerStore();

    $payout = app(LedgerService::class)->requestPayout($store, 15000);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->call('approve', $payout->id)
        ->call('openMarkPaid', $payout->id)
        ->set('paidReference', 'MBB-20260612-042')
        ->call('markPaid')
        ->assertHasNoErrors();

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->ledgerEntries()->sole()->amount_sen)->toBe(-15000) // entry stays
        ->and($store->availableBalanceSen())->toBe(4500); // no double debit
});
