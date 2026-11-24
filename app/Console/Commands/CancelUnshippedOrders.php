<?php

namespace App\Console\Commands;

use App\Models\SubOrder;
use App\Notifications\UnshippedOrderWarningNotification;
use App\Services\OrderService;
use App\Settings\OrderSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * PAY-14 / HB-G143 (HB-DEC-1124d (2)) — hourly. A sub-order still not shipped
 * `unshipped_cancel_days` after its order was placed (online: after payment)
 * is cancelled through OrderService::cancel (restock, vouchers/coins back,
 * paid online → full refund, buyer + seller notified). Day
 * `unshipped_warn_days`: one warning to the seller. Elapsed time, so the
 * Malaysia/UTC split does not move the deadline.
 */
class CancelUnshippedOrders extends Command
{
    protected $signature = 'orders:cancel-unshipped';

    protected $description = 'Warn sellers about, then cancel, sub-orders not shipped in time';

    public function handle(OrderService $orders, OrderSettings $settings): int
    {
        $cancelDays = $settings->unshipped_cancel_days;
        $reason = __('Not shipped within :days days', ['days' => $cancelDays]);

        $this->unshipped(now()->subDays($cancelDays))
            ->chunkById(100, function ($subOrders) use ($orders, $reason) {
                foreach ($subOrders as $subOrder) {
                    // One row that throws (e.g. a refund leg) must not stall
                    // every other cancel behind it; it is retried next hour.
                    try {
                        if ($orders->cancelIfUnshipped($subOrder, $reason)) {
                            $this->info("Cancelled {$subOrder->sub_order_no}.");
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $this->error("Could not cancel {$subOrder->sub_order_no}: {$e->getMessage()}");
                    }
                }
            });

        $daysLeft = $cancelDays - $settings->unshipped_warn_days;

        $this->unshipped(now()->subDays($settings->unshipped_warn_days))
            ->whereNull('unshipped_warned_at')
            ->chunkById(100, function ($subOrders) use ($daysLeft) {
                foreach ($subOrders as $subOrder) {
                    // Claim the warning under the row lock; a ship or an
                    // overlapping run in between means no (second) warning.
                    $claimed = DB::transaction(function () use ($subOrder) {
                        // Parent order first, like every sub-order lock (gate R3, #225).
                        $locked = SubOrder::lockForUpdateWithOrder($subOrder)->first();

                        if ($locked === null || ! in_array($locked->status, OrderService::UNSHIPPED, true) || $locked->unshipped_warned_at !== null) {
                            return false;
                        }

                        $locked->forceFill(['unshipped_warned_at' => now()])->save();

                        return true;
                    });

                    if ($claimed) {
                        $subOrder->store->user?->notify(new UnshippedOrderWarningNotification($subOrder, $daysLeft));
                        $this->info("Warned the seller of {$subOrder->sub_order_no}.");
                    }
                }
            });

        return self::SUCCESS;
    }

    /** Not shipped, and the clock (paid_at, else placed_at) started on or before $cutoff. */
    private function unshipped(\DateTimeInterface $cutoff): Builder
    {
        return SubOrder::query()
            ->whereIn('status', OrderService::UNSHIPPED)
            ->whereHas('order', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('paid_at', '<=', $cutoff)
                ->orWhere(fn (Builder $q) => $q->whereNull('paid_at')->where('placed_at', '<=', $cutoff))));
    }
}
