<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
use App\Models\SubOrder;
use Illuminate\Support\Facades\DB;

/**
 * Fulfilment-side transitions that carry side effects beyond the status
 * change itself (restock, COD settlement). All go through SubOrderStatusService.
 */
class OrderService
{
    public function __construct(private SubOrderStatusService $statusService) {}

    /**
     * Cancel a sub-order (buyer pre-ship, seller, admin, or system) and
     * restock its items atomically.
     */
    public function cancel(SubOrder $subOrder, ActorType $actor, ?int $actorId = null, ?string $reason = null): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $actor, $actorId, $reason) {
            $subOrder = $this->statusService->transition($subOrder, SubOrderStatus::Cancelled, $actor, $actorId, $reason);

            if ($reason !== null) {
                $subOrder->forceFill(['cancel_reason' => $reason])->save();
            }

            $this->restock($subOrder);
            $this->refundCoinsIfFullyCancelled($subOrder);

            return $subOrder;
        });
    }

    /**
     * When the LAST active sub-order of an unpaid order is cancelled, return any
     * coins the buyer redeemed at checkout (idempotent). Paid orders keep their
     * redemption — coin handling on post-payment refunds is out of M2.1 scope.
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
            $subOrder = $this->statusService->transition($subOrder, SubOrderStatus::Delivered, $actor, $actorId);

            $order = $subOrder->order;

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

            return $subOrder;
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
        foreach ($subOrder->items()->with('variant')->get() as $item) {
            if ($item->variant !== null) {
                app(StockService::class)->apply($item->variant, $item->qty, StockMovementType::Restock, $subOrder->sub_order_no);
            }
        }
    }
}
