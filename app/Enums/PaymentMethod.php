<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Ipay88 = 'ipay88';

    public function label(): string
    {
        return match ($this) {
            self::Cod => __('Cash on delivery'),
            self::Ipay88 => __('Online payment'),
        };
    }
}
