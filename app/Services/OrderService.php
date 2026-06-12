<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
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

            return $subOrder;
        });
    }

    /**
     * Mark delivered. COD orders settle their payment here (docs/06 §C).
     */
    public function markDelivered(SubOrder $subOrder, ActorType $actor, ?int $actorId = null): SubOrder
    {
        return DB::transaction(function () use ($subOrder, $actor, $actorId) {
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
                }
            }

            return $subOrder;
        });
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
                $item->variant->increment('stock', $item->qty);
            }
        }
    }
}
