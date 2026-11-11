<?php

namespace App\Enums;

enum LedgerEntryStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case PaidOut = 'paid_out';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('On hold'),
            self::Available => __('Available'),
            self::PaidOut => __('Paid out'),
        };
    }
}
