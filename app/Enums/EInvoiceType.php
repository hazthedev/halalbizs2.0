<?php

namespace App\Enums;

/**
 * Individual = one e-invoice for one sub-order (buyer requested it, or the
 * order exceeds the individual threshold). Consolidated = one monthly B2C
 * document aggregating a store's un-requested receipts.
 */
enum EInvoiceType: string
{
    case Individual = 'individual';
    case Consolidated = 'consolidated';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('Individual'),
            self::Consolidated => __('Consolidated'),
        };
    }
}
