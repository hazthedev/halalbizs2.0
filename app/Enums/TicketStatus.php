<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Answered => __('Answered'),
            self::Closed => __('Closed'),
        };
    }
}
