<?php

namespace App\Listeners;

use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Notifications\AffiliateCommissionNotification;
use App\Services\AffiliateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Books affiliate commission when a referred sub-order completes (M2.5) —
 * aligned with the seller-ledger and coin completion hooks. Queued, so it runs
 * outside the transaction that completed the order and cannot roll it back.
 * Idempotent per sub-order, which is what makes retrying it safe.
 */
class RecordAffiliateCommissionOnCompletion implements ShouldQueue
{
    public $queue = 'affiliate';

    /**
     * M-12: three tries with backoff, and NO try/catch around the body.
     *
     * This used to swallow Throwable and return normally, so the queue recorded
     * success and `--tries=3` on the worker was dead config — the one recovery
     * mechanism these have was disabled by the thing meant to protect them. The
     * docblock justified it as "a failure must never roll back a completed
     * order", but that already holds: this is ShouldQueue, so it runs OUTSIDE
     * the transaction that completed the order. Letting it throw fails the JOB,
     * not the order.
     */
    public $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(private AffiliateService $affiliates) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to !== SubOrderStatus::Completed || ! $this->affiliates->enabled()) {
            return;
        }
        $referral = $this->affiliates->recordCommission($event->subOrder);

        if ($referral !== null) {
            $referral->affiliate->user?->notify(
                new AffiliateCommissionNotification($referral->commission_sen, $referral->locks_at),
            );
        }
    }
}
