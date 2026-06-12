<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => __('Normal'),
            self::Urgent => __('Urgent'),
        };
    }
}
