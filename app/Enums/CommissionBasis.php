<?php

namespace App\Enums;

/**
 * What the platform's commission is charged ON (docs/09 §A).
 *
 * Only SELLER-funded discounts are in question. Platform-funded vouchers never
 * reduce the base under either basis — the platform absorbs those and the
 * seller is paid in full, so charging on the full price is correct regardless.
 */
enum CommissionBasis: string
{
    /** List price of the goods, before any seller-funded discount. */
    case Gross = 'gross';

    /** What the seller was actually paid for the goods, after their own discounts. */
    case Net = 'net';

    public function label(): string
    {
        return match ($this) {
            self::Gross => __('Gross — list price, before seller discounts'),
            self::Net => __('Net — what the seller was actually paid'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Gross => __('A seller discount is their own marketing spend; the platform fee does not move. Charged on the list price, so a deep seller voucher can cost them more than the sale earns.'),
            self::Net => __('Seller-funded discounts shrink the fee with the sale. The platform never charges on money the seller did not receive.'),
        };
    }
}
