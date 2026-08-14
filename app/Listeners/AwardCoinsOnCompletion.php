<?php

namespace App\Listeners;

use App\Enums\CoinTransactionType;
use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Notifications\CoinsEarnedNotification;
use App\Services\CoinService;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        $subOrder = $event->subOrder;
        $buyer = $subOrder->order?->user;

        if ($buyer === null) {
            return;
        }

        $perRm = (int) config('coins.earn_coins_per_rm', 1);
        $coins = intdiv((int) $subOrder->items_subtotal_sen, 100) * $perRm;

        if ($coins <= 0) {
            return;
        }

        $credited = $this->coins->credit(
            $buyer,
            $coins,
            CoinTransactionType::Earn,
            $subOrder,
            __('Coins earned on order :no', ['no' => $subOrder->sub_order_no]),
        );

        // Notify only when coins were actually credited (idempotent: a repeat
        // completion returns null and stays silent).
        if ($credited !== null) {
            $buyer->notify(new CoinsEarnedNotification($coins, $subOrder->sub_order_no));
        }
    }
}
