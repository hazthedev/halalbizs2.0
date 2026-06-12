<?php

use App\Support\Money;

test('formats sen as ringgit', function () {
    expect(Money::format(125000))->toBe('RM 1,250.00')
        ->and(Money::format(50))->toBe('RM 0.50')
        ->and(Money::format(0))->toBe('RM 0.00')
        ->and(Money::format(5))->toBe('RM 0.05')
        ->and(Money::format(100))->toBe('RM 1.00')
        ->and(Money::format(123456789))->toBe('RM 1,234,567.89');
});

test('formats negative amounts', function () {
    expect(Money::format(-2500))->toBe('-RM 25.00');
});

test('formats zero-decimal currencies', function () {
    expect(Money::format(345000, 'Rp', 0))->toBe('Rp 345,000');
});
