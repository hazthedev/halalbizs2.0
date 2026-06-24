<?php

namespace App\Enums;

enum AffiliatePayoutStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Paid => __('Paid'),
            self::Rejected => __('Rejected'),
        };
    }
}
