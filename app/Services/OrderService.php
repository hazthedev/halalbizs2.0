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
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fulfilment-side transitions that carry side effects beyond the status
 * change itself (restock, COD settlement). All go through SubOrderStatusService.
 */
class OrderService
{
    public function __construct(
        private SubOrderStatusService $statusService,
        private RefundService $refunds,
        private GroupBuyService $groupBuy,
    ) {}

    /**
     * Cancel a sub-order (buyer pre-ship, seller, admin, or system) and
     * restock its items atomically.
     */
    public function cancel(SubOrder $subOrder, ActorType $actor, ?int $actorId = null, ?string $reason = null): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $actor, $actorId, $reason) {
            // Gate R2 (#225): lock the parent order first (payment confirm and
            // expiry use the same order-before-sub-order order), so sibling
            // cancels serialise and the last one sees every other sibling's
            // committed status.
            Order::whereKey($subOrder->order_id)->lockForUpdate()->first();

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
                    // HB-G003: the group-buy seat comes back on every cancel,
                    // paid or not (a paid cancel is refunded in full below).
                    $this->groupBuy->releaseSeat($cancelled);
                    $this->releaseEntitlementsIfUnpaid($cancelled);
                    $this->refundCoinsIfFullyCancelled($cancelled);
                    $this->refundIfAlreadyPaid($cancelled, $actor, $actorId, $reason);
                },
            );
        });
    }

    /**
     * Cancel every still-unpaid sub-order of an order that is awaiting
     * payment, in ONE transaction (docs/06 §F: no partial cancel before
     * payment). The buyer's "cancel order" button and the API both call this;
     * each sub-order still goes through cancel() → the status service.
     */
    public function cancelUnpaid(Order $order, ActorType $actor, ?int $actorId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $actor, $actorId, $reason) {
            Order::whereKey($order->id)->lockForUpdate()->first();
            $pending = $order->subOrders()->where('status', SubOrderStatus::PendingPayment)->lockForUpdate()->get();

            foreach ($pending as $subOrder) {
                $this->cancel($subOrder, $actor, $actorId, $reason);
            }
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
     * HB-VOUCHER-D1 (Haze 2026-11-17 12:15): an order cancelled or expired
     * before payment gives back what checkout consumed: the shop voucher of
     * this sub-order, and the platform and shipping vouchers (order-level
     * rows) once the whole order is cancelled. The group-buy seat is released
     * by cancel() for every cancel, paid or not (HB-G003). Same lock order as
     * checkout (variants, seats, vouchers, then wallet). Each deleted
     * usage row gives back exactly one use, so a replay gives back nothing.
     */
    private function releaseEntitlementsIfUnpaid(SubOrder $subOrder): void
    {
        $order = $subOrder->order->fresh();

        if ($order === null || ! in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            return;
        }

        $fullyCancelled = ! $order->subOrders()->where('status', '!=', SubOrderStatus::Cancelled)->lockForUpdate()->exists();

        $usages = VoucherUsage::where('order_id', $order->id)
            ->where(fn ($q) => $q->where('sub_order_id', $subOrder->id)->when($fullyCancelled, fn ($q) => $q->orWhereNull('sub_order_id')))
            ->get();

        if ($usages->isEmpty()) {
            return;
        }

        Voucher::whereIn('id', $usages->pluck('voucher_id')->unique())->orderBy('id')->lockForUpdate()->get();

        foreach ($usages as $usage) {
            if (VoucherUsage::whereKey($usage->id)->delete() === 1) {
                Voucher::whereKey($usage->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        }
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
            ->lockForUpdate() // current read, not the transaction's snapshot (gate R2)
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
            // Only shipped → delivered. The FSM also allows return_requested →
            // delivered, but that edge is the admin side-with-seller path;
            // taking it here would strand the buyer's open return.
            if (SubOrder::lockForUpdateWithOrder($subOrder)->first()->status !== SubOrderStatus::Shipped) {
                throw new InvalidArgumentException(__('Only a shipped order can be marked delivered.'));
            }

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
