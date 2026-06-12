<?php

namespace App\Enums;

enum ActorType: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => __('Buyer'),
            self::Seller => __('Seller'),
            self::Admin => __('Admin'),
            self::System => __('System'),
        };
    }
}
