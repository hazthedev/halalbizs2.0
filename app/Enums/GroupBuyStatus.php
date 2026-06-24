<?php

namespace App\Enums;

enum GroupBuyStatus: string
{
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Ended => __('Ended'),
        };
    }
}
