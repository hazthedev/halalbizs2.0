<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Ipay88 = 'ipay88';

    /**
     * The rails a buyer can actually pay on, for display (e.g. footer chips).
     * The reference design shows COD / DUITNOW / CARD / PICKUP; this app has COD
     * and iPay88, whose real rails are FPX, cards and e-wallets. Advertising a
     * method that cannot be used is worse than showing fewer.
     *
     * @return array<int, string>
     */
    public function rails(): array
    {
        return match ($this) {
            self::Cod => ['COD'],
            self::Ipay88 => ['FPX', __('Card'), __('E-wallet')],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cod => __('Cash on delivery'),
            self::Ipay88 => __('Online payment'),
        };
    }
}
