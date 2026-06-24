<?php

namespace App\Enums;

enum GroupBuyTeamStatus: string
{
    case Forming = 'forming';     // still recruiting members
    case Unlocked = 'unlocked';   // target reached — members can buy at the deal price
    case Expired = 'expired';     // window closed before unlocking

    public function label(): string
    {
        return match ($this) {
            self::Forming => __('Forming'),
            self::Unlocked => __('Unlocked'),
            self::Expired => __('Expired'),
        };
    }
}
