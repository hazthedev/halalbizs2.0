<?php

use App\Enums\CoinTransactionType;
use App\Exceptions\CheckoutException;
use App\Models\CoinTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CoinService;

beforeEach(function () {
    config(['coins.enabled' => true, 'coins.redemption_rate_sen' => 1, 'coins.expiry_days' => 180]);
});

test('earning credits balance + lifetime and is idempotent per reference', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $svc = app(CoinService::class);

    $svc->credit($user, 100, CoinTransactionType::Earn, $product, 'first');
    $svc->credit($user, 100, CoinTransactionType::Earn, $product, 'duplicate'); // same ref → skipped

    expect($svc->balance($user))->toBe(100)
        ->and($user->coinWallet->fresh()->lifetime_earned)->toBe(100);
});

test('redeeming spends FIFO and honours the per-order sen cap', function () {
    config(['coins.max_redemption_sen' => 5000]);
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $svc->credit($user, 8000, CoinTransactionType::Earn);

    // Bill RM100 (10000 sen); cap 5000 sen → 5000 coins consumed.
    $result = $svc->redeemForCheckout($user, 8000, 10000);

    expect($result->coins)->toBe(5000)
        ->and($result->sen)->toBe(5000)
        ->and($svc->balance($user))->toBe(3000);
});

test('redemption always leaves at least one sen payable', function () {
    config(['coins.max_redemption_sen' => 1_000_000]);
    $user = User::factory()->create();
    app(CoinService::class)->credit($user, 5000, CoinTransactionType::Earn);

    $result = app(CoinService::class)->redeemForCheckout($user, 5000, 2000);

    expect($result->sen)->toBe(1999); // 2000 − 1
});

test('expiry removes lapsed lots from the balance exactly once', function () {
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $svc->credit($user, 100, CoinTransactionType::Earn);

    CoinTransaction::where('type', CoinTransactionType::Earn)->update(['expires_at' => now()->subDay()]);

    expect($svc->expireDue())->toBe(100)
        ->and($svc->balance($user))->toBe(0)
        ->and($svc->expireDue())->toBe(0); // nothing left to expire
});

test('spent lots are not re-expired', function () {
    config(['coins.max_redemption_sen' => 1_000_000]);
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $svc->credit($user, 100, CoinTransactionType::Earn);
    $svc->redeemForCheckout($user, 100, 100_000); // spend all 100

    CoinTransaction::where('type', CoinTransactionType::Earn)->update(['expires_at' => now()->subDay()]);

    expect($svc->expireDue())->toBe(0)
        ->and($svc->balance($user))->toBe(0);
});

test('daily check-in awards coins and blocks a second check-in the same day', function () {
    $user = User::factory()->create();
    $svc = app(CoinService::class);

    $first = $svc->checkIn($user);

    expect($first['streak'])->toBe(1)
        ->and($svc->balance($user))->toBe($first['coins'])
        ->and($svc->canCheckInToday($user))->toBeFalse();

    $svc->checkIn($user);
})->throws(CheckoutException::class);

test('a consecutive-day check-in increments the streak', function () {
    $user = User::factory()->create();
    $svc = app(CoinService::class);

    $svc->checkIn($user);
    $user->coinWallet->update(['last_checkin_on' => now()->subDay(), 'checkin_streak' => 1]);

    expect($svc->checkIn($user)['streak'])->toBe(2);
});

test('refunding a cancelled order returns redeemed coins exactly once', function () {
    config(['coins.max_redemption_sen' => 1_000_000]);
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $svc->credit($user, 1000, CoinTransactionType::Earn);

    $order = Order::factory()->create(['user_id' => $user->id, 'coin_redemption_sen' => 300]);
    $result = $svc->redeemForCheckout($user, 300, 10_000);
    $result->transaction->forceFill([
        'reference_type' => $order->getMorphClass(),
        'reference_id' => $order->id,
    ])->save();

    expect($svc->balance($user))->toBe(700);

    $svc->refundForOrder($order);
    $svc->refundForOrder($order); // idempotent

    expect($svc->balance($user))->toBe(1000);
});

test('the feature is inert when disabled', function () {
    config(['coins.enabled' => false]);
    $user = User::factory()->create();

    app(CoinService::class)->credit($user, 100, CoinTransactionType::Earn);

    expect(app(CoinService::class)->balance($user))->toBe(0);
});
