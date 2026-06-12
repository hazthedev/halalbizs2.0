<?php

namespace App\Enums;

enum VoucherType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::Percent => __('Percentage'),
            self::FreeShipping => __('Free shipping'),
        };
    }
}
