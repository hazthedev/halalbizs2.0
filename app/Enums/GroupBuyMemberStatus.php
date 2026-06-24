<?php

namespace App\Enums;

enum GroupBuyMemberStatus: string
{
    case Joined = 'joined';        // reserved a spot, not yet purchased
    case Purchased = 'purchased';  // checked out at the group price

    public function label(): string
    {
        return match ($this) {
            self::Joined => __('Joined'),
            self::Purchased => __('Purchased'),
        };
    }
}
