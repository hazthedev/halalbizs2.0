<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Live = 'live';
    case Delisted = 'delisted';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingReview => __('Pending review'),
            self::Live => __('Live'),
            self::Delisted => __('Delisted'),
            self::Banned => __('Banned'),
        };
    }
}
