<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\SubOrder;
use Illuminate\Support\Facades\DB;

/**
 * Fulfilment-side transitions that carry side effects beyond the status
 * change itself (restock, COD settlement). All go through SubOrderStatusService.
 */
class OrderService
{
    public function __construct(
        private SubOrderStatusService $statusService,
        private RefundService $refunds,
    ) {}

    /**
     * Cancel a sub-order (buyer pre-ship, seller, admin, or system) and
     * restock its items atomically.
     */
    public function cancel(SubOrder $subOrder, ActorType $actor, ?int $actorId = null, ?string $reason = null): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $actor, $actorId, $reason) {
            return $this->statusService->transition(
                $subOrder,
                SubOrderStatus::Cancelled,
                $actor,
                $actorId,
                $reason,
                function (SubOrder $cancelled) use ($actor, $actorId, $reason) {
                    if ($reason !== null) {
                        $cancelled->forceFill(['cancel_reason' => $reason])->save();
                    }

                    $this->restock($cancelled);
                    $this->refundCoinsIfFullyCancelled($cancelled);
                    $this->refundIfAlreadyPaid($cancelled, $actor, $actorId, $reason);
                },
            );
        });
    }

    /**
     * Cancelling a sub-order the buyer has already PAID for owes them the money
     * back (audit H-3).
     *
     * For iPay88 a sub-order only reaches Confirmed after settlement, and
     * Confirmed is exactly what the buyer's cancel button allows — so this is
     * the ordinary "changed my mind after paying" path, not an edge case.
     * Before this, cancel() did restock() and refundCoinsIfFullyCancelled()
     * and nothing else: the goods went back on the shelf, the platform kept
     * the cash, payment_status still read Paid, refunded_sen stayed 0 on both
     * rows, and so the debt appeared on no reconciliation surface at all.
     *
     * markRefunded: false deliberately. The sub-order genuinely IS cancelled —
     * the goods were restocked, it was never returned — and 'cancelled' is a
     * terminal state in the transition map by design. We want the money engine,
     * not the status change: RefundService still writes the cash, coins, ledger
     * reversal, affiliate clawback and both refunded_sen columns.
     *
     * Idempotent by construction: refund() re-reads under lockForUpdate and
     * caps at total_sen - refunded_sen, so a double-cancel refunds once.
     */
    private function refundIfAlreadyPaid(SubOrder $subOrder, ActorType $actor, ?int $actorId, ?string $reason): void
    {
        if ($subOrder->order?->payment_status !== PaymentStatus::Paid) {
            return;
        }

        $this->refunds->refund(
            $subOrder,
            (int) $subOrder->total_sen,
            $actor,
            $actorId,
            $reason !== null ? __('Cancelled after payment — :reason', ['reason' => $reason]) : __('Cancelled after payment'),
            markRefunded: false,
        );
    }

    /**
     * When the LAST active sub-order of an unpaid order is cancelled, return any
     * coins the buyer redeemed at checkout (idempotent). Paid orders keep their
     * redemption — coin handling on post-payment refunds is out of M2.1 scope.
     *
     * A PAID order's coins are handled by refundIfAlreadyPaid() instead:
     * RefundService::reverseForRefund puts them back pro-rata to the cash.
     */
    private function refundCoinsIfFullyCancelled(SubOrder $subOrder): void
    {
        $order = $subOrder->order->fresh();

        if ($order === null
            || $order->payment_status === PaymentStatus::Paid
            || (int) $order->coin_redemption_sen <= 0) {
            return;
        }

        $stillActive = $order->subOrders()
            ->where('status', '!=', SubOrderStatus::Cancelled)
            ->exists();

        if (! $stillActive) {
            app(CoinService::class)->refundForOrder($order);
        }
    }

    /**
     * Mark delivered. COD orders settle their payment here (docs/06 §C).
     */
    public function markDelivered(SubOrder $subOrder, ActorType $actor, ?int $actorId = null): SubOrder
    {
        $becamePaid = false;

        $subOrder = DB::transaction(function () use ($subOrder, $actor, $actorId, &$becamePaid) {
            return $this->statusService->transition(
                $subOrder,
                SubOrderStatus::Delivered,
                $actor,
                $actorId,
                null,
                function (SubOrder $delivered) use (&$becamePaid) {
                    // M-9: lock the parent ORDER before deciding COD settlement.
                    // transition() locks this sub-order, but the question below
                    // is about its SIBLINGS — and it was asked unlocked. Two
                    // sub-orders of the same order delivered concurrently could
                    // both see "everything else is terminal", both flip
                    // payment_status, and both dispatch OrderPaid, which is what
                    // triggers e-invoicing.
                    $order = Order::whereKey($delivered->order_id)->lockForUpdate()->first();

                    if ($order->payment_method === PaymentMethod::Cod && $order->payment_status === PaymentStatus::Pending) {
                        $allDeliveredOrBetter = $order->subOrders()
                            ->whereNotIn('status', [
                                SubOrderStatus::Cancelled,
                                SubOrderStatus::Delivered,
                                SubOrderStatus::Completed,
                            ])
                            ->doesntExist();

                        if ($allDeliveredOrBetter) {
                            $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
                            $order->payment?->update(['status' => GatewayPaymentStatus::Success, 'paid_at' => now()]);
                            $becamePaid = true;
                        }
                    }
                },
            );
        });

        // After commit: e-invoicing fires on the verified-paid event.
        if ($becamePaid) {
            OrderPaid::dispatch($subOrder->order->fresh());
        }

        return $subOrder;
    }

    /**
     * Buyer confirms receipt → completed (starts the payout clock; the
     * ledger hook arrives in M8 via the SubOrderStatusChanged event).
     */
    public function confirmReceived(SubOrder $subOrder, int $buyerId): SubOrder
    {
        return $this->statusService->transition($subOrder, SubOrderStatus::Completed, ActorType::Buyer, $buyerId);
    }

    private function restock(SubOrder $subOrder): void
    {
        foreach ($subOrder->items()->with(['variant', 'flashSaleItem'])->get() as $item) {
            if ($item->variant !== null) {
                app(StockService::class)->apply($item->variant, $item->qty, StockMovementType::Restock, $subOrder->sub_order_no);
            }

            // Release any flash-sale allocation this line consumed, so a cancelled
            // or expired-unpaid order doesn't permanently burn the deal's stock.
            if ($item->flashSaleItem !== null) {
                $item->flashSaleItem->decrement('sold_qty', min($item->qty, (int) $item->flashSaleItem->sold_qty));
            }
        }
    }
}
