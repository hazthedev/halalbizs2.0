<?php

use App\Models\AffiliatePayout;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\AffiliateService;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

// HB-G198 (HB-DEC-1124f item 6): bank account numbers, webhook secrets and
// users.tin are stored encrypted with APP_KEY; the app still reads plain values.

function rawColumn(string $table, int $id, string $column): string
{
    return (string) DB::table($table)->where('id', $id)->value($column);
}

test('store bank details are stored encrypted and read back plain', function () {
    $store = Store::factory()->create(['bank_details' => ['bank_name' => 'Maybank', 'account_name' => 'Ali Foods', 'account_number' => '5123456789']]);

    $raw = rawColumn('stores', $store->id, 'bank_details');

    expect($raw)->not->toContain('5123456789')
        ->and(json_decode(Crypt::decryptString($raw), true)['account_number'])->toBe('5123456789')
        ->and($store->fresh()->bank_details['account_number'])->toBe('5123456789')
        ->and($store->fresh()->bankDetailsDiffer(['bank_name' => 'Maybank', 'account_name' => 'Ali Foods', 'account_number' => '5123456789']))->toBeFalse();
});

test('payout bank snapshots are stored encrypted', function () {
    $store = Store::factory()->approved()->create(['bank_details' => ['bank_name' => 'CIMB', 'account_name' => 'A', 'account_number' => '7000111222']]);
    $payout = Payout::create(['payout_no' => Payout::generatePayoutNo(), 'store_id' => $store->id, 'amount_sen' => 5000, 'status' => 'requested',
        'bank_snapshot' => $store->bank_details, 'requested_at' => now()]);

    expect(rawColumn('payouts', $payout->id, 'bank_snapshot'))->not->toContain('7000111222')
        ->and($payout->fresh()->bank_snapshot['account_number'])->toBe('7000111222');
});

test('affiliate payout bank snapshots are stored encrypted', function () {
    $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    $payout = AffiliatePayout::create(['affiliate_id' => $affiliate->id, 'amount_sen' => 6000, 'status' => 'requested',
        'bank_snapshot' => ['details' => 'Maybank 5123456789'], 'requested_at' => now()]);

    expect(rawColumn('affiliate_payouts', $payout->id, 'bank_snapshot'))->not->toContain('5123456789')
        ->and($payout->fresh()->bank_snapshot['details'])->toBe('Maybank 5123456789');
});

test('webhook secrets are stored encrypted and never sit plain in the queue', function () {
    config(['queue.default' => 'database']);
    $subscription = WebhookSubscription::create(['url' => 'https://example.com/hook', 'secret' => 'whsec_plain_value_42', 'events' => ['order.paid'], 'is_active' => true]);

    app(WebhookDispatcher::class)->dispatch('order.paid', ['id' => 1]);

    expect(rawColumn('webhook_subscriptions', $subscription->id, 'secret'))->not->toContain('whsec_plain_value_42')
        ->and($subscription->fresh()->secret)->toBe('whsec_plain_value_42')
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('payload'))->not->toContain('whsec_plain_value_42');
});

test('a user tin is stored encrypted', function () {
    $user = User::factory()->create(['tin' => 'IG12345678090']);

    expect(rawColumn('users', $user->id, 'tin'))->not->toContain('IG12345678090')
        ->and($user->fresh()->tin)->toBe('IG12345678090');
});
