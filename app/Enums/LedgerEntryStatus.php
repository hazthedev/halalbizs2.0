<?php

namespace App\Enums;

enum LedgerEntryStatus: string
{
    case Available = 'available';
    case PaidOut = 'paid_out';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::PaidOut => __('Paid out'),
        };
    }
}
