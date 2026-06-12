<?php

namespace App\Enums;

enum TwoFactorMethod: string
{
    case Email = 'email';
    case Totp = 'totp';

    public function label(): string
    {
        return match ($this) {
            self::Email => __('Email code'),
            self::Totp => __('Authenticator app'),
        };
    }
}
