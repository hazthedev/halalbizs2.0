<?php

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Settings\OrderSettings;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('GET seller/earnings returns balances, the paginated ledger and payouts', function () {
    $seller = sellerApiUser();
    $store = $seller->store;

    $store->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 20500, 'status' => LedgerEntryStatus::Available, 'description' => 'Sale SO1']);
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Commission, 'amount_sen' => -1000, 'status' => LedgerEntryStatus::Available, 'description' => 'Commission 5% on SO1']);

    $response = sellerApiAs($seller)->getJson('/api/v1/seller/earnings')
        ->assertOk()
        ->assertJsonPath('data.available_sen', 19500)
        ->assertJsonPath('data.held_sen', 0)
        ->assertJsonPath('data.paid_out_sen', 0)
        ->assertJsonPath('data.payout_min_sen', app(OrderSettings::class)->payout_min_sen)
        ->assertJsonPath('data.open_payout', null)
        ->assertJsonPath('data.payouts', [])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonCount(2, 'data.ledger');

    $commission = collect($response->json('data.ledger'))->firstWhere('type', 'commission');
    expect($commission)->toHaveKeys(['id', 'type', 'type_label', 'amount_sen', 'reference', 'created_at'])
        ->and($commission['amount_sen'])->toBe(-1000)
        ->and($commission['type_label'])->toBe('Commission');
});

test('POST seller/payouts requests a payout; below the minimum and a second open request are 409 payout_rejected', function () {
    $seller = sellerApiUser();
    $store = $seller->store;
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 30000, 'status' => LedgerEntryStatus::Available, 'description' => 'Sale']);

    $minSen = app(OrderSettings::class)->payout_min_sen;

    sellerApiAs($seller)->postJson('/api/v1/seller/payouts', ['amount_sen' => $minSen - 1])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'payout_rejected');

    sellerApiAs($seller)->postJson('/api/v1/seller/payouts', ['amount_sen' => 'ten'])
        ->assertStatus(422);

    $response = sellerApiAs($seller)->postJson('/api/v1/seller/payouts', ['amount_sen' => 10000])
        ->assertCreated()
        ->assertJsonPath('data.amount_sen', 10000)
        ->assertJsonPath('data.status', 'requested');

    $payout = Payout::find($response->json('data.id'));
    expect($payout->store_id)->toBe($store->id)
        ->and($payout->status)->toBe(PayoutStatus::Requested)
        ->and($store->fresh()->availableBalanceSen())->toBe(20000);

    sellerApiAs($seller)->postJson('/api/v1/seller/payouts', ['amount_sen' => 10000])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'payout_rejected')
        ->assertJsonPath('error.message', __('You already have a payout in progress.'));

    sellerApiAs($seller)->getJson('/api/v1/seller/earnings')
        ->assertJsonPath('data.available_sen', 20000)
        ->assertJsonPath('data.open_payout.id', $payout->id)
        ->assertJsonPath('data.payouts.0.payout_no', $payout->payout_no);
});
