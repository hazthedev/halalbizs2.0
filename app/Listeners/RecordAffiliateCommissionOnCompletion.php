<?php

namespace App\Listeners;

use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Notifications\AffiliateCommissionNotification;
use App\Services\AffiliateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Books affiliate commission when a referred sub-order completes (M2.5) —
 * aligned with the seller-ledger and coin completion hooks. Queued + defensive:
 * never rolls back a completed order. Idempotent per sub-order.
 */
class RecordAffiliateCommissionOnCompletion implements ShouldQueue
{
    public $queue = 'affiliate';

    public function __construct(private AffiliateService $affiliates) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to !== SubOrderStatus::Completed || ! $this->affiliates->enabled()) {
            return;
        }

        try {
            $referral = $this->affiliates->recordCommission($event->subOrder);

            if ($referral !== null) {
                $referral->affiliate->user?->notify(new AffiliateCommissionNotification($referral->commission_sen));
            }
        } catch (Throwable $e) {
            Log::error('Affiliate commission booking failed.', [
                'sub_order' => $event->subOrder->sub_order_no ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
