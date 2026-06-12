<?php

namespace App\Enums;

enum ProductCondition: string
{
    case New = 'new';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Used => __('Used'),
        };
    }
}
