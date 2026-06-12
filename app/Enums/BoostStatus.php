<?php

namespace App\Enums;

enum BoostStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Expired => __('Expired'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
