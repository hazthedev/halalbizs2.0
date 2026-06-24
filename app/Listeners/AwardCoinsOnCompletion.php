<?php

namespace App\Listeners;

use App\Enums\CoinTransactionType;
use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Notifications\CoinsEarnedNotification;
use App\Services\CoinService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Loyalty Coins are earned when a sub-order completes (M2.1), aligned with the
 * seller-ledger completion hook and the escrow model. Queued + defensive: a
 * coin-earn failure can never roll back a completed order. Idempotent per
 * sub-order via CoinService::credit().
 */
class AwardCoinsOnCompletion implements ShouldQueue
{
    public $queue = 'coins';

    public function __construct(private CoinService $coins) {}

    public function handle(SubOrderStatusChanged $event): void
    {
        if ($event->to !== SubOrderStatus::Completed || ! $this->coins->enabled()) {
            return;
        }

        try {
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
        } catch (Throwable $e) {
            Log::error('Coin earn failed.', [
                'sub_order' => $event->subOrder->sub_order_no ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
