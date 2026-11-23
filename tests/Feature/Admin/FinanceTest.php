<?php

use App\Enums\PayoutStatus;
use App\Livewire\Admin\Finance\Commission;
use App\Livewire\Admin\Finance\Payouts;
use App\Models\Category;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Settings\CommissionSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function financeAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    makeAdmin($user);

    return $user;
}

/** Direct Payout fixture — payout requests themselves arrive with M8. */
function financePayout(PayoutStatus $status = PayoutStatus::Requested, array $attributes = []): Payout
{
    return Payout::create(array_merge([
        'payout_no' => Payout::generatePayoutNo(),
        'store_id' => Store::factory()->approved()->create()->id,
        'amount_sen' => 1250,
        'status' => $status,
        'bank_snapshot' => [
            'bank_name' => 'Maybank',
            'account_name' => 'Acme Trading',
            'account_number' => '1234567890',
        ],
        'requested_at' => now()->subDay(),
    ], $status === PayoutStatus::Approved ? ['approved_at' => now()] : [], $attributes));
}

test('finance screens are admin-only', function () {
    $this->seed(RoleSeeder::class);

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route('admin.finance.commission'))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.finance.payouts'))->assertForbidden();

    $admin = financeAdmin();
    $this->actingAs($admin)->get(route('admin.finance.commission'))->assertOk();
    $this->actingAs($admin)->get(route('admin.finance.payouts'))->assertOk();
});

test('saving the global commission rate persists it', function () {
    $admin = financeAdmin();

    Livewire::actingAs($admin)
        ->test(Commission::class)
        ->set('globalRate', '150')
        ->call('saveGlobalRate')
        ->assertHasErrors(['globalRate' => 'max'])
        ->set('globalRate', '7.25')
        ->call('saveGlobalRate')
        ->assertHasNoErrors();

    expect(app(CommissionSettings::class)->refresh()->global_rate)->toBe(7.25);
});

test('effective-rate tester resolves store override > category chain > global default', function () {
    $admin = financeAdmin();

    $settings = app(CommissionSettings::class);
    $settings->global_rate = 5.00;
    $settings->save();

    $overrideStore = Store::factory()->approved()->create(['commission_rate' => 12.5]);
    $plainStore = Store::factory()->approved()->create(['commission_rate' => null]);

    $parent = Category::factory()->create(['name' => ['en' => 'Electronics', 'ms' => 'Elektronik'], 'commission_rate' => 8.0]);
    $leaf = Category::factory()->create(['parent_id' => $parent->id, 'commission_rate' => null]);
    $plainCategory = Category::factory()->create(['commission_rate' => null]);

    Livewire::actingAs($admin)
        ->test(Commission::class)
        // Store override beats everything.
        ->set('testerStoreId', (string) $overrideStore->id)
        ->set('testerCategoryId', (string) $leaf->id)
        ->assertSee('Store override 12.5%')
        // No store override → the chain walks up to the parent's rate.
        ->set('testerStoreId', (string) $plainStore->id)
        ->assertSee('Category chain: Electronics 8%')
        // No rate anywhere on the chain → global default.
        ->set('testerCategoryId', (string) $plainCategory->id)
        ->assertSee('Global default 5%');
});

test('payout approve, reject and mark paid transitions', function () {
    $admin = financeAdmin();
    $payout = financePayout();
    $toReject = financePayout();

    $component = Livewire::actingAs($admin)->test(Payouts::class);

    // Approve: requested → approved, stamped and attributed.
    $component->call('approve', $payout->id);
    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Approved)
        ->and($payout->approved_at)->not->toBeNull()
        ->and($payout->processed_by)->toBe($admin->id);

    // Reject needs a reason.
    $component
        ->call('openReject', $toReject->id)
        ->call('reject')
        ->assertHasErrors(['rejectReason' => 'required']);
    expect($toReject->refresh()->status)->toBe(PayoutStatus::Requested);

    $component
        ->set('rejectReason', 'Bank details do not match the verified documents')
        ->call('reject')
        ->assertHasNoErrors();
    $toReject->refresh();
    expect($toReject->status)->toBe(PayoutStatus::Rejected)
        ->and($toReject->reference)->toBe('Bank details do not match the verified documents')
        ->and($toReject->processed_by)->toBe($admin->id);

    // Mark paid: approved → paid, with the bank reference.
    $component
        ->set('tab', PayoutStatus::Approved->value)
        ->call('openMarkPaid', $payout->id)
        ->call('markPaid')
        ->assertHasErrors(['paidReference' => 'required'])
        ->set('paidReference', 'MBB-20260612-001')
        ->call('markPaid')
        ->assertHasNoErrors();

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->reference)->toBe('MBB-20260612-001');
});

test('bank CSV export streams selected approved payouts with integer-math RM amounts', function () {
    $admin = financeAdmin();

    $approved = financePayout(PayoutStatus::Approved, ['amount_sen' => 1250]);
    $requested = financePayout(); // selected but not approved — must never export

    $component = Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value)
        ->set('selected', [(string) $approved->id, (string) $requested->id])
        ->call('exportBankCsv')
        ->assertFileDownloaded();

    $csv = base64_decode((string) data_get($component->effects, 'download.content'));

    expect($csv)->toContain('account_number,account_name,bank,amount_rm,payout_no')
        ->toContain('12.50') // 1250 sen → "12.50" via sprintf('%d.%02d')
        ->toContain($approved->payout_no)
        ->toContain('1234567890')
        ->toContain('Maybank')
        ->not->toContain($requested->payout_no);
});

/** Ids the approved tab's header "select all" box would tick (its data-ids attribute). */
function payoutSelectAllIds($component): array
{
    preg_match('/data-ids="([^"]*)"/', $component->html(), $m);

    return array_map('intval', json_decode(html_entity_decode($m[1] ?? 'null'), true) ?? []);
}

function payoutCsv($component): string
{
    return base64_decode((string) data_get($component->effects, 'download.content'));
}

test('HB-G168: select all under a search picks only the payout the search shows, and the export acts on it alone', function () {
    $admin = financeAdmin();
    $shown = financePayout(PayoutStatus::Approved);
    $hiddenA = financePayout(PayoutStatus::Approved);
    $hiddenB = financePayout(PayoutStatus::Approved);

    $component = Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value)
        ->set('search', $shown->payout_no);

    $ids = payoutSelectAllIds($component);
    expect($ids)->toBe([$shown->id]);

    $component->set('selected', array_map('strval', $ids))
        ->assertSee('1 payout selected')
        ->call('exportBankCsv')
        ->assertFileDownloaded();

    expect(payoutCsv($component))->toContain($shown->payout_no)
        ->not->toContain($hiddenA->payout_no)
        ->not->toContain($hiddenB->payout_no);
});

test('HB-G168: select all on page 1 of 2 picks only the page-1 payouts', function () {
    $admin = financeAdmin();
    // Default order is requested_at asc: the 20 oldest are page 1 at the default 20 per page.
    $payouts = collect(range(1, 21))->map(fn (int $i) => financePayout(PayoutStatus::Approved, ['requested_at' => now()->subDays(30 - $i)]));

    $component = Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value);

    expect(payoutSelectAllIds($component))->toBe($payouts->take(20)->pluck('id')->all());

    $component->set('selected', array_map('strval', payoutSelectAllIds($component)))
        ->assertSee('20 payouts selected')
        ->call('exportBankCsv');

    expect(payoutCsv($component))->not->toContain($payouts->last()->payout_no)
        ->toContain($payouts->first()->payout_no);

    // Turning the page clears the ticks; page 2's box carries only page 2's row.
    $component->call('nextPage')->assertSet('selected', []);
    expect(payoutSelectAllIds($component))->toBe([$payouts->last()->id]);
});

test('HB-G168: a forged selection is checked id by id — bad ids are skipped, valid approved ids still export', function () {
    $admin = financeAdmin();
    $approved = financePayout(PayoutStatus::Approved);
    $other = financePayout(PayoutStatus::Approved);
    $requested = financePayout();
    $paid = financePayout(PayoutStatus::Paid);

    $component = Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value)
        ->set('selected', [(string) $approved->id, (string) $requested->id, (string) $paid->id, '999999999', $other->id.'x'])
        ->call('exportBankCsv')
        ->assertFileDownloaded();

    expect(payoutCsv($component))->toContain($approved->payout_no)
        ->not->toContain($requested->payout_no)
        ->not->toContain($paid->payout_no)
        ->not->toContain($other->payout_no); // "<id>x" is not an id, never read as one

    // Status is untouched by the export: it is a read, nothing is written.
    expect($requested->refresh()->status)->toBe(PayoutStatus::Requested)
        ->and($paid->refresh()->status)->toBe(PayoutStatus::Paid);
});

test('HB-G168: a forged select-all flag cannot select payouts the screen does not show', function () {
    $admin = financeAdmin();
    $shown = financePayout(PayoutStatus::Approved);
    financePayout(PayoutStatus::Approved);

    $component = Livewire::actingAs($admin)
        ->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value)
        ->set('search', $shown->payout_no)
        ->set('selectPage', true);

    expect($component->get('selected'))->toBe([(string) $shown->id]);
    expect(fn () => $component->set('selectAll', true))->toThrow(Exception::class);
});

/**
 * HB-G224 race: a second admin acts between this admin's read and write.
 * The first time [$payout] is loaded, [$other] runs (as the other admin
 * would) before the approve/mark-paid write lands.
 */
function payoutRaceOnFirstRead(Payout $payout, Closure $other): void
{
    $fired = false;

    Payout::retrieved(function (Payout $loaded) use ($payout, $other, &$fired) {
        if ($fired || $loaded->id !== $payout->id) {
            return;
        }

        $fired = true;
        $other();
    });
}

test('HB-G224: approve refuses a payout another admin rejected after it was read — the reversal stays, status stays rejected', function () {
    $admin = financeAdmin();
    $payout = financePayout();
    $payout->ledgerEntries()->create([
        'store_id' => $payout->store_id,
        'type' => \App\Enums\LedgerEntryType::Payout,
        'amount_sen' => -1250,
        'status' => \App\Enums\LedgerEntryStatus::Available,
        'description' => 'Payout earmark',
    ]);

    $component = Livewire::actingAs($admin)->test(Payouts::class);

    payoutRaceOnFirstRead($payout, fn () => app(\App\Services\LedgerService::class)->rejectPayout(Payout::find($payout->id), 'Other admin'));

    $component->call('approve', $payout->id)
        ->assertDispatched('toast', type: 'error');

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->approved_at)->toBeNull()
        ->and((int) $payout->ledgerEntries()->sum('amount_sen'))->toBe(0);
});

test('HB-G224: mark paid refuses a payout another admin rejected after it was read', function () {
    $admin = financeAdmin();
    $payout = financePayout(PayoutStatus::Approved);

    $component = Livewire::actingAs($admin)->test(Payouts::class)
        ->set('tab', PayoutStatus::Approved->value)
        ->call('openMarkPaid', $payout->id)
        ->set('paidReference', 'MBB-RACE-001');

    payoutRaceOnFirstRead($payout, fn () => app(\App\Services\LedgerService::class)->rejectPayout(Payout::find($payout->id), 'Other admin'));

    $component->call('markPaid')->assertDispatched('toast', type: 'error');

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->paid_at)->toBeNull()
        ->and($payout->reference)->toBe('Other admin');
});

test('HB-G224: approve and mark paid on a payout no longer in the right status change nothing', function () {
    $admin = financeAdmin();
    $rejected = financePayout(PayoutStatus::Rejected);
    $requested = financePayout();

    Livewire::actingAs($admin)->test(Payouts::class)
        ->call('approve', $rejected->id)
        ->assertDispatched('toast', type: 'error')
        ->call('openMarkPaid', $requested->id)
        ->set('paidReference', 'MBB-NOPE-001')
        ->call('markPaid')
        ->assertDispatched('toast', type: 'error');

    expect($rejected->refresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($rejected->approved_at)->toBeNull()
        ->and($requested->refresh()->status)->toBe(PayoutStatus::Requested)
        ->and($requested->paid_at)->toBeNull();
});

test('HB-G224: reject refuses a payout another admin paid after it was read — no success toast, processed_by kept', function () {
    $admin = financeAdmin();
    $other = financeAdmin();
    $payout = financePayout();

    $component = Livewire::actingAs($admin)->test(Payouts::class)
        ->call('openReject', $payout->id)
        ->set('rejectReason', 'Bank details do not match');

    payoutRaceOnFirstRead($payout, fn () => Payout::whereKey($payout->id)->update([
        'status' => PayoutStatus::Paid,
        'paid_at' => now(),
        'reference' => 'MBB-PAID-001',
        'processed_by' => $other->id,
    ]));

    $component->call('reject')->assertDispatched('toast', type: 'error');

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->processed_by)->toBe($other->id)
        ->and($payout->reference)->toBe('MBB-PAID-001')
        ->and($payout->ledgerEntries()->count())->toBe(0);
});
