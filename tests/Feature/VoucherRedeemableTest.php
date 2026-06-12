<?php

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;

function makeVoucher(array $overrides = []): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Platform,
        'code' => 'TEST'.fake()->unique()->numerify('####'),
        'type' => VoucherType::Fixed,
        'value_sen' => 500,
        'min_spend_sen' => 0,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ], $overrides));
}

test('redeemable inside window with quota and min spend met', function () {
    $user = User::factory()->create();

    expect(makeVoucher()->isRedeemableBy($user, 1000))->toBeTrue();
});

test('not redeemable when inactive, outside window, or under min spend', function () {
    $user = User::factory()->create();

    expect(makeVoucher(['is_active' => false])->isRedeemableBy($user, 1000))->toBeFalse()
        ->and(makeVoucher(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)])->isRedeemableBy($user, 1000))->toBeFalse()
        ->and(makeVoucher(['ends_at' => now()->subHour(), 'starts_at' => now()->subDay()])->isRedeemableBy($user, 1000))->toBeFalse()
        ->and(makeVoucher(['min_spend_sen' => 5000])->isRedeemableBy($user, 4999))->toBeFalse()
        ->and(makeVoucher(['min_spend_sen' => 5000])->isRedeemableBy($user, 5000))->toBeTrue();
});

test('quota exhaustion blocks redemption', function () {
    $user = User::factory()->create();
    $voucher = makeVoucher(['quota' => 2, 'used_count' => 2]);

    expect($voucher->isRedeemableBy($user, 1000))->toBeFalse();
});

test('per-user limit blocks repeat redemption', function () {
    $user = User::factory()->create();
    $voucher = makeVoucher(['per_user_limit' => 1]);

    $voucher->usages()->create([
        'user_id' => $user->id,
        'discount_sen' => 500,
        'created_at' => now(),
    ]);

    expect($voucher->isRedeemableBy($user, 1000))->toBeFalse()
        ->and($voucher->isRedeemableBy(User::factory()->create(), 1000))->toBeTrue();
});

test('discount math: fixed caps at subtotal, percent caps at max discount', function () {
    expect(makeVoucher(['value_sen' => 500])->discountSenFor(300))->toBe(300)
        ->and(makeVoucher(['value_sen' => 500])->discountSenFor(10000))->toBe(500);

    $percent = makeVoucher(['type' => VoucherType::Percent, 'value_sen' => null, 'percent' => 10, 'max_discount_sen' => 700]);
    expect($percent->discountSenFor(5000))->toBe(500)
        ->and($percent->discountSenFor(20000))->toBe(700);
});
