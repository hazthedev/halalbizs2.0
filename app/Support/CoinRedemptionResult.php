<?php

namespace App\Support;

use App\Models\CoinTransaction;

/**
 * Outcome of redeeming coins inside the checkout transaction: how many coins
 * were consumed, their sen value off the bill, and the debit ledger row (so the
 * caller can backfill the order reference once the order exists).
 */
final class CoinRedemptionResult
{
    public function __construct(
        public readonly int $coins,
        public readonly int $sen,
        public readonly ?CoinTransaction $transaction,
    ) {}

    public static function none(): self
    {
        return new self(0, 0, null);
    }
}
