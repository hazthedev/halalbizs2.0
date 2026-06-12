<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin sub-order detail (docs/08 §E) — a read-only mirror of the seller
 * view (snapshots are sacred: items, prices and address are never edited)
 * plus the two admin powers:
 *
 * - Force-cancel: any state the transition map allows into `cancelled`
 *   (pending_payment / confirmed / processing) via OrderService::cancel,
 *   which restocks atomically.
 * - Mark refunded: only from `return_requested` or `returned` per the
 *   transition map. The actual money moves in the iPay88 merchant portal,
 *   so the admin records the portal reference here.
 */
#[Layout('layouts.admin')]
class Detail extends Component
{
    public SubOrder $subOrder;

    public string $cancelReason = '';

    public string $refundReference = '';

    public function mount(SubOrder $subOrder): void
    {
        $this->subOrder = $subOrder->load(['items.product.media', 'order.user', 'order.payment', 'store', 'statusHistories']);
    }

    /** Admin force-cancel — restocks items via OrderService (docs/08 §E). */
    public function forceCancel(): void
    {
        $this->validate(
            ['cancelReason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['cancelReason' => __('cancellation reason')],
        );

        if (! app(SubOrderStatusService::class)->canTransition($this->subOrder, SubOrderStatus::Cancelled)) {
            $this->dispatch('toast', message: __('This sub-order can no longer be cancelled from its current status.'), type: 'error');

            return;
        }

        app(OrderService::class)->cancel($this->subOrder, ActorType::Admin, auth()->id(), trim($this->cancelReason));

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Sub-order cancelled — items are back in stock.'));
    }

    /**
     * Mark refunded with the iPay88 portal reference.
     *
     * Only `return_requested` and `returned` may enter `refunded` (transition
     * map — a cancelled-after-payment case is deliberately NOT refundable here;
     * it would need a map change first). GatewayPaymentStatus has no refunded
     * case — the gateway record keeps its final status and the refund is
     * expressed as: order-level payment_status → refunded, the portal
     * reference stored on the payment's requery_result (surfaces in the
     * reconciliation grid), and the reference in the status-history note.
     */
    public function markRefunded(): void
    {
        $this->validate(
            ['refundReference' => ['required', 'string', 'min:3', 'max:100']],
            [],
            ['refundReference' => __('iPay88 portal reference')],
        );

        if (! app(SubOrderStatusService::class)->canTransition($this->subOrder, SubOrderStatus::Refunded)) {
            $this->dispatch('toast', message: __('Only return-requested or returned sub-orders can be marked refunded.'), type: 'error');

            return;
        }

        $reference = trim($this->refundReference);

        DB::transaction(function () use ($reference) {
            app(SubOrderStatusService::class)->transition(
                $this->subOrder,
                SubOrderStatus::Refunded,
                ActorType::Admin,
                auth()->id(),
                __('Refunded via iPay88 portal — ref :ref', ['ref' => $reference]),
            );

            $order = $this->subOrder->order;
            $order->update(['payment_status' => PaymentStatus::Refunded]);
            $order->payment?->update(['requery_result' => 'refunded: '.$reference]);
        });

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Marked refunded — reference recorded.'));
    }

    public function render()
    {
        $statusService = app(SubOrderStatusService::class);

        return view('livewire.admin.orders.detail', [
            'canForceCancel' => $statusService->canTransition($this->subOrder, SubOrderStatus::Cancelled),
            'canMarkRefunded' => $statusService->canTransition($this->subOrder, SubOrderStatus::Refunded),
            // "5.00" → "5", "5.50" → "5.5" for the commission line.
            'commissionRate' => rtrim(rtrim(number_format((float) $this->subOrder->commission_rate, 2, '.', ''), '0'), '.'),
        ])->title($this->subOrder->sub_order_no);
    }

    private function refreshSubOrder(): void
    {
        $this->subOrder = $this->subOrder->fresh(['items.product.media', 'order.user', 'order.payment', 'store', 'statusHistories']);
        $this->reset('cancelReason', 'refundReference');
        $this->resetValidation();
    }
}
