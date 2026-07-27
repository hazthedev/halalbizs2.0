<?php

use App\Services\Ipay88Service;
use App\Settings\Ipay88Settings;

function setMerchantCode(string $code): void
{
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = $code;
    $settings->save();
}

test('a blank merchant code does NOT enable the mock outside local without the opt-in flag', function () {
    setMerchantCode(''); // as an unconfigured production boot would leave it
    config(['services.ipay88.allow_mock' => false]);

    // The test environment is not "local", so the free-settlement simulator is
    // fail-closed — a real production boot can never hand out free paid orders.
    expect(app(Ipay88Service::class)->isMock())->toBeFalse();
});

test('the mock is reachable only when explicitly opted in via IPAY88_ALLOW_MOCK', function () {
    setMerchantCode('');
    config(['services.ipay88.allow_mock' => true]); // a preview opts in on its own .env

    expect(app(Ipay88Service::class)->isMock())->toBeTrue();
});

test('a configured merchant code never runs the mock, flag or not', function () {
    setMerchantCode('M00001');
    config(['services.ipay88.allow_mock' => true]);

    expect(app(Ipay88Service::class)->isMock())->toBeFalse();
});
