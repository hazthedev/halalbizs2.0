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
    $user->assignRole('admin');

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
