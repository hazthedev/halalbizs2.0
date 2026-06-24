<?php

namespace App\Enums;

/**
 * Replenishment cadences for subscribe-and-save (M2.8). Backed by day counts.
 */
enum SubscriptionInterval: int
{
    case Weekly = 7;
    case Fortnightly = 14;
    case Monthly = 30;
    case Bimonthly = 60;

    public function days(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Weekly => __('Every week'),
            self::Fortnightly => __('Every 2 weeks'),
            self::Monthly => __('Every month'),
            self::Bimonthly => __('Every 2 months'),
        };
    }
}
