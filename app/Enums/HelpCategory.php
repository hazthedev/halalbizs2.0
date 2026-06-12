<?php

namespace App\Enums;

enum HelpCategory: string
{
    case Buying = 'buying';
    case Selling = 'selling';
    case Payments = 'payments';
    case Shipping = 'shipping';
    case Returns = 'returns';
    case Account = 'account';

    public function label(): string
    {
        return match ($this) {
            self::Buying => __('Buying'),
            self::Selling => __('Selling'),
            self::Payments => __('Payments'),
            self::Shipping => __('Shipping'),
            self::Returns => __('Returns & refunds'),
            self::Account => __('Account'),
        };
    }
}
