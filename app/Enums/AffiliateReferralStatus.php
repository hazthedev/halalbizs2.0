<?php

namespace App\Enums;

/**
 * The commission lifecycle. Policy vocabulary is pending -> locked -> paid; the
 * stored value for "locked" stays `confirmed` because that is what every
 * existing row already holds and what `availableForPayoutSen()` has always
 * counted. Renaming it would be a stored-value migration whose only product is
 * a nicer word, and a half-applied one silently zeroes every creator's balance.
 */
enum AffiliateReferralStatus: string
{
    /** Booked at completion, visible to the creator, NOT payable yet. */
    case Pending = 'pending';

    /** Locked/approved: the hold has elapsed, this counts toward payout. */
    case Confirmed = 'confirmed';

    /** Voided — the sale it was booked against came back. */
    case Reversed = 'reversed';

    /** Withdrawn in a payout cycle. */
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Available'),
            self::Reversed => __('Reversed'),
            self::Paid => __('Paid'),
        };
    }

    /** Statuses whose commission counts toward a withdrawal. */
    public static function payable(): array
    {
        return [self::Confirmed];
    }
}
