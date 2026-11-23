<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case Shipping = 'shipping';
    case Adjustment = 'adjustment';
    case Payout = 'payout';
    case PayoutReversal = 'payout_reversal';
    case CodOffset = 'cod_offset';
    case Boost = 'boost';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('Sale'),
            self::Commission => __('Commission'),
            self::Shipping => __('Shipping'),
            self::Adjustment => __('Adjustment'),
            self::Payout => __('Payout'),
            self::PayoutReversal => __('Payout reversal'),
            self::CodOffset => __('COD offset'),
            self::Boost => __('Boost'),
            self::Settlement => __('Settlement'),
        };
    }
}
