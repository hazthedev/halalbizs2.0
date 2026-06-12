<?php

namespace App\Enums;

enum VoucherScope: string
{
    case Platform = 'platform';
    case Shop = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::Platform => __('Platform'),
            self::Shop => __('Shop'),
        };
    }
}
