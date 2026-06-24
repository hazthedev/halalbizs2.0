<?php

namespace App\Enums;

/**
 * Coin ledger entry kinds (M2.1). Credits create an expiring FIFO lot;
 * debits consume the oldest live lots first.
 */
enum CoinTransactionType: string
{
    case Earn = 'earn';             // order completion
    case Checkin = 'checkin';       // daily check-in
    case Spin = 'spin';             // spin-to-win coins
    case Refund = 'refund';         // returned on a cancelled unpaid order
    case Adjustment = 'adjustment'; // admin grant/clawback
    case Redeem = 'redeem';         // spent at checkout
    case Expire = 'expire';         // lapsed lot remainder

    public function label(): string
    {
        return match ($this) {
            self::Earn => __('Earned'),
            self::Checkin => __('Daily check-in'),
            self::Spin => __('Spin reward'),
            self::Refund => __('Refunded'),
            self::Adjustment => __('Adjustment'),
            self::Redeem => __('Redeemed'),
            self::Expire => __('Expired'),
        };
    }

    /** Credits open a fresh expiring lot; debits draw down existing lots. */
    public function isCredit(): bool
    {
        return in_array($this, [self::Earn, self::Checkin, self::Spin, self::Refund], true);
    }

    /** Only genuine earnings count toward lifetime totals (not refunds). */
    public function countsLifetime(): bool
    {
        return in_array($this, [self::Earn, self::Checkin, self::Spin], true);
    }
}
