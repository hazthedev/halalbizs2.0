<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Disputed = 'disputed';
    case Escalated = 'escalated';
    case Refunded = 'refunded';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => __('Requested'),
            self::Accepted => __('Accepted'),
            self::Disputed => __('Disputed'),
            self::Escalated => __('Escalated'),
            self::Refunded => __('Refunded'),
            self::Rejected => __('Rejected'),
        };
    }
}
