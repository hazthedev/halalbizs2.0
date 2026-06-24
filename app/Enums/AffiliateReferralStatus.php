<?php

namespace App\Enums;

enum AffiliateReferralStatus: string
{
    case Confirmed = 'confirmed'; // referred order completed — commission earned
    case Reversed = 'reversed';   // order later refunded/returned
    case Paid = 'paid';           // commission withdrawn

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => __('Confirmed'),
            self::Reversed => __('Reversed'),
            self::Paid => __('Paid'),
        };
    }
}
