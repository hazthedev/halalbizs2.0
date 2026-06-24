<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
