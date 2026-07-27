<?php

namespace App\Listeners;

use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Services\LedgerService;

/**
 * The payout clock starts at completion (docs/09 §A).
 *
 * DESIGN DECISION (H3/M1, 2026-07-27): kept SYNCHRONOUS and NOT ShouldQueue,
 * unlike its siblings AwardCoinsOnCompletion / RecordAffiliateCommissionOnCompletion.
 * Those are cosmetic/secondary (loyalty coins, affiliate payout) and are
 * deliberately best-effort — never rolling back a completed order is correct
 * for them. The seller ledger Sale/Commission booking is not secondary: it IS
 * the financial settlement `Completed` is supposed to represent, and
 * `completed` has no path back except `ReturnRequested` — an un-booked
 * Completed sub-order was previously unrecoverable (H3). So instead of
 * queueing this listener, `SubOrderStatusService::transition()` now wraps the
 * status save + history write + event dispatch in ONE `DB::transaction()`
 * (see SubOrderStatusService.php). Firing synchronously inside that
 * transaction means a throwing `recordCompletion()` rolls the status change
 * back too — the sub-order stays `Delivered` and the transition is safely
 * retryable (by the buyer or the next cron run), rather than stranding a
 * `Completed` order with no ledger entries. Do not make this ShouldQueue
 * without also removing the transactional guarantee above.
 */
class RecordLedgerOnCompletion
{
    public function __construct(private LedgerService $ledger) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to === SubOrderStatus::Completed) {
            $this->ledger->recordCompletion($event->subOrder);
        }
    }
}
