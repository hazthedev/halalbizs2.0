<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Services\AffiliateService;
use Illuminate\Console\Command;

/**
 * Promotes held affiliate commission to payable once its window has passed.
 *
 * The hold is what makes the refund policy humane: a refund arriving before this
 * runs just reduces a `pending` number the creator was told is not theirs yet,
 * so there is no clawback conversation. Everything that escapes the window is
 * the exceptional case (slice 2's carry-forward ledger).
 *
 * Idempotent — it only ever moves rows whose locks_at is already in the past, so
 * a missed run catches up on the next tick rather than losing anything.
 */
class LockAffiliateCommissions extends Command
{
    protected $signature = 'affiliates:lock-commissions';

    protected $description = 'Promote affiliate commissions whose hold period has elapsed';

    public function handle(AffiliateService $affiliates): int
    {
        if (! $affiliates->enabled()) {
            $this->info('Affiliate program disabled — nothing to do.');

            return self::SUCCESS;
        }

        $locked = $affiliates->lockDueCommissions();

        $this->info($locked === 0
            ? 'No commissions due to unlock.'
            : "Unlocked {$locked} commission(s).");

        return self::SUCCESS;
    }
}
