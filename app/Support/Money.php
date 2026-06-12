<?php

namespace App\Support;

use Brick\Money\Money as BrickMoney;

/**
 * All money is integer sen. This helper is the only formatting path —
 * no float arithmetic anywhere (CLAUDE.md hard rule 1).
 */
class Money
{
    public static function of(int $sen, string $currency = 'MYR'): BrickMoney
    {
        return BrickMoney::ofMinor($sen, $currency);
    }

    /**
     * Integer-only formatting: "RM 1,250.00" from 125000 sen.
     */
    public static function format(int $sen, string $symbol = 'RM', int $decimals = 2): string
    {
        $negative = $sen < 0;
        $abs = abs($sen);
        $divisor = 10 ** $decimals;
        $units = intdiv($abs, $divisor);
        $minor = $abs % $divisor;

        $formatted = number_format($units);

        if ($decimals > 0) {
            $formatted .= '.'.str_pad((string) $minor, $decimals, '0', STR_PAD_LEFT);
        }

        return ($negative ? '-' : '').$symbol.' '.$formatted;
    }
}
