<?php

namespace App\Enums;

enum LiveSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Live => __('Live'),
            self::Ended => __('Ended'),
        };
    }
}
