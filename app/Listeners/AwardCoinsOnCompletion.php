<?php

namespace App\Listeners;

use App\Enums\CoinTransactionType;
use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Models\SubOrder;
use App\Notifications\CoinsEarnedNotification;
use App\Services\CoinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Loyalty Coins are earned when a sub-order completes (M2.1), aligned with the
 * seller-ledger completion hook and the escrow model. Queued, so a coin-earn
 * failure cannot roll back a completed order — it runs outside that
 * transaction. Idempotent per sub-order via CoinService::credit(), which is
 * what makes retrying it safe.
 */
class AwardCoinsOnCompletion implements ShouldQueue
{
    public $queue = 'coins';

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

    public function __construct(private CoinService $coins) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to !== SubOrderStatus::Completed || ! $this->coins->enabled()) {
            return;
        }

        // HB-G129: earn only on what was not refunded. The event's sub-order is a
        // snapshot from completion (no SerializesModels), and a refund can land
        // before this job runs, so re-read refunded_sen under the sub-order lock
        // RefundService takes: the refund then either sees this Earn row and
        // takes back its share, or lands first and is netted out here.
        [$buyer, $credited] = DB::transaction(function () use ($event) {
            $subOrder = SubOrder::lockForUpdateWithOrder($event->subOrder)->first();
            $buyer = $subOrder?->order?->user;
            $coins = $buyer === null ? 0 : $this->coins->earnedCoinsFor($subOrder, (int) $subOrder->refunded_sen);

            return [$buyer, $coins <= 0 ? null : $this->coins->credit(
                $buyer,
                $coins,
                CoinTransactionType::Earn,
                $subOrder,
                __('Coins earned on order :no', ['no' => $subOrder->sub_order_no]),
            )];
        });

        // Notify only when coins were actually credited (idempotent: a repeat
        // completion returns null and stays silent).
        if ($credited !== null) {
            $buyer->notify(new CoinsEarnedNotification((int) $credited->amount, $event->subOrder->sub_order_no));
        }
    }
}
