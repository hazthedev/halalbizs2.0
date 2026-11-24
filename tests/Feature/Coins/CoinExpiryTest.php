<?php

use App\Enums\CoinTransactionType;
use App\Models\CoinTransaction;
use App\Models\User;
use App\Notifications\CoinsExpiringNotification;
use App\Services\CoinService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

// BIZ-04 (HB-DEC-1124f item 8): coins expire 12 months after they are earned,
// with one reminder 30 days before.

beforeEach(fn () => config(['coins.enabled' => true]));

afterEach(fn () => Carbon::setTestNow());

test('an earned lot expires twelve calendar months after it is earned', function () {
    Carbon::setTestNow('2027-02-28 09:00:00');
    $user = User::factory()->create();

    $lot = app(CoinService::class)->credit($user, 100, CoinTransactionType::Earn);

    // Twelve months, not 365 days: 2028 is a leap year.
    expect($lot->expires_at->toDateTimeString())->toBe('2028-02-28 09:00:00');
});

test('the daily command expires a lapsed lot with a ledger entry, not a delete', function () {
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $lot = $svc->credit($user, 100, CoinTransactionType::Earn);
    $lot->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('coins:expire')->assertSuccessful();

    expect($lot->fresh()->remaining)->toBe(0)
        ->and(CoinTransaction::where('type', CoinTransactionType::Expire)->sole()->amount)->toBe(-100)
        ->and($svc->balance($user))->toBe(0);
});

test('a buyer is reminded once, 30 days before a lot expires', function () {
    Notification::fake();
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $soon = $svc->credit($user, 120, CoinTransactionType::Earn);
    $alsoSoon = $svc->credit($user, 30, CoinTransactionType::Checkin);
    $later = $svc->credit($user, 50, CoinTransactionType::Spin);
    $soon->forceFill(['expires_at' => now()->addDays(29)])->save();
    $alsoSoon->forceFill(['expires_at' => now()->addDays(10)])->save();
    $later->forceFill(['expires_at' => now()->addDays(31)])->save();

    $this->artisan('coins:expire')->assertSuccessful();
    $this->artisan('coins:expire')->assertSuccessful(); // the next day's run: already reminded

    Notification::assertSentToTimes($user, CoinsExpiringNotification::class, 1);
    Notification::assertSentTo($user, CoinsExpiringNotification::class, fn ($n) => $n->coins === 150);
    expect($soon->fresh()->expiry_reminded_at)->not->toBeNull()
        ->and($later->fresh()->expiry_reminded_at)->toBeNull();
});

test('a lot already spent gets no reminder', function () {
    Notification::fake();
    $user = User::factory()->create();
    $svc = app(CoinService::class);
    $lot = $svc->credit($user, 100, CoinTransactionType::Earn);
    $lot->forceFill(['expires_at' => now()->addDays(5), 'remaining' => 0])->save();

    $this->artisan('coins:expire')->assertSuccessful();

    Notification::assertNothingSent();
});
