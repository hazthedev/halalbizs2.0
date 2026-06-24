<?php

use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\User;
use App\Services\CoinService;
use App\Services\SpinService;

beforeEach(fn () => config(['coins.enabled' => true]));

test('a coins prize credits the wallet', function () {
    $user = User::factory()->create();

    $outcome = app(SpinService::class)->grant($user, ['type' => 'coins', 'coins' => 20]);

    expect($outcome['type'])->toBe('coins')
        ->and($outcome['coins'])->toBe(20)
        ->and(app(CoinService::class)->balance($user))->toBe(20);
});

test('a fixed-voucher prize mints a single-use platform voucher', function () {
    $user = User::factory()->create();

    $outcome = app(SpinService::class)->grant($user, ['type' => 'voucher', 'voucher' => 'fixed', 'value_sen' => 500]);

    expect($outcome['type'])->toBe('voucher')
        ->and($outcome['voucher']->type)->toBe(VoucherType::Fixed)
        ->and($outcome['voucher']->value_sen)->toBe(500)
        ->and($outcome['voucher']->quota)->toBe(1)
        ->and($outcome['voucher']->per_user_limit)->toBe(1)
        ->and($outcome['voucher']->code)->toStartWith('SPIN');
});

test('a free-shipping prize mints a free-shipping voucher', function () {
    $user = User::factory()->create();

    $outcome = app(SpinService::class)->grant($user, ['type' => 'voucher', 'voucher' => 'free_shipping']);

    expect($outcome['voucher']->type)->toBe(VoucherType::FreeShipping);
});

test('every deck draw resolves to a valid grant', function () {
    $user = User::factory()->create();
    $spin = app(SpinService::class);

    foreach ((array) config('coins.spin_deck') as $slot) {
        $outcome = $spin->grant($user, $slot);
        expect($outcome)->toHaveKey('label')->and($outcome['label'])->not->toBe('');
    }
});

test('spinning is limited to once per calendar day', function () {
    $user = User::factory()->create();
    app(SpinService::class)->spin($user);

    expect(app(SpinService::class)->canSpinToday($user))->toBeFalse();

    app(SpinService::class)->spin($user);
})->throws(CheckoutException::class);
