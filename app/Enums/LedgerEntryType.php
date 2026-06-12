<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case Shipping = 'shipping';
    case Adjustment = 'adjustment';
    case Payout = 'payout';
    case CodOffset = 'cod_offset';
    case Boost = 'boost';

    public function label(): string
    {
        return match ($this) {
            self::Sale => __('Sale'),
            self::Commission => __('Commission'),
            self::Shipping => __('Shipping'),
            self::Adjustment => __('Adjustment'),
            self::Payout => __('Payout'),
            self::CodOffset => __('COD offset'),
            self::Boost => __('Boost'),
        };
    }
}
