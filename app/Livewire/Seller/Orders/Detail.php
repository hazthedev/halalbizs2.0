<?php

namespace App\Livewire\Seller\Orders;

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Livewire\Seller\Orders\Concerns\ManagesShipment;
use App\Models\CancellationReason;
use App\Models\SubOrder;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller sub-order detail (docs/07 §B) — snapshot items, address card,
 * totals + commission, status timeline, and the fulfilment actions.
 * Every status change goes through the services (CLAUDE.md hard rule 2).
 */
#[Layout('layouts.seller')]
class Detail extends Component
{
    use CurrentStore, ManagesShipment;

    public SubOrder $subOrder;

    public ?int $cancelReasonId = null;

    public string $disputeReason = '';

    public bool $disputing = false;

    public function mount(SubOrder $subOrder): void
    {
        // Leakage guard: another store's sub-order is a 403, not a redirect.
        $this->authorizeStore($subOrder->store_id);

        $this->subOrder = $subOrder->load(['items.product.media', 'order.user', 'statusHistories', 'returnRequest.reason', 'returnRequest.media']);
    }

    /** confirmed → processing. */
    public function confirmAndPack(): void
    {
        $statusService = app(SubOrderStatusService::class);

        if (! $statusService->canTransition($this->subOrder, SubOrderStatus::Processing)) {
            return;
        }

        $statusService->transition($this->subOrder, SubOrderStatus::Processing, ActorType::Seller, auth()->id());

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Order confirmed — pack it, then arrange shipment.'));
    }

    /** Pre-ship cancel with a reason — restocks via OrderService. */
    public function cancelOrder(): void
    {
        $this->validate(
            ['cancelReasonId' => ['required', 'integer', 'exists:cancellation_reasons,id']],
            [],
            ['cancelReasonId' => __('cancellation reason')],
        );

        if (! app(SubOrderStatusService::class)->canTransition($this->subOrder, SubOrderStatus::Cancelled)) {
            return;
        }

        $reason = CancellationReason::query()->findOrFail($this->cancelReasonId);

        app(OrderService::class)->cancel(
            $this->subOrder,
            ActorType::Seller,
            auth()->id(),
            $reason->getTranslation('label', app()->getLocale()),
        );

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Order cancelled — items are back in stock.'));
    }

    /** shipped → delivered (v1: seller/system may mark delivered, docs M4). */
    public function markDelivered(): void
    {
        if ($this->subOrder->status !== SubOrderStatus::Shipped) {
            return;
        }

        app(OrderService::class)->markDelivered($this->subOrder, ActorType::Seller, auth()->id());

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Marked as delivered.'));
    }

    /**
     * Step 1 of the accept path (docs/09 §D): the request flips to accepted
     * and the buyer ships the item back (manual v1). The sub-order stays
     * return_requested until the seller confirms receipt.
     */
    public function acceptReturn(): void
    {
        $request = $this->subOrder->returnRequest;

        if ($this->subOrder->status !== SubOrderStatus::ReturnRequested || $request?->status !== ReturnStatus::Requested) {
            return;
        }

        $request->update(['status' => ReturnStatus::Accepted]);

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Return accepted — the buyer will ship the item back to you.'));
    }

    /** Step 2: item came back → returned. The refund itself is an admin task. */
    public function confirmItemReceived(): void
    {
        $request = $this->subOrder->returnRequest;

        if ($this->subOrder->status !== SubOrderStatus::ReturnRequested || $request?->status !== ReturnStatus::Accepted) {
            return;
        }

        app(SubOrderStatusService::class)->transition(
            $this->subOrder,
            SubOrderStatus::Returned,
            ActorType::Seller,
            auth()->id(),
            __('Returned item received by the seller'),
        );

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Item received — our team will process the refund.'));
    }

    /** Dispute → straight to the admin queue. The sub-order status does not move. */
    public function disputeReturn(): void
    {
        $this->validate(
            ['disputeReason' => ['required', 'string', 'min:10', 'max:1000']],
            [],
            ['disputeReason' => __('dispute reason')],
        );

        $request = $this->subOrder->returnRequest;

        if ($this->subOrder->status !== SubOrderStatus::ReturnRequested || $request?->status !== ReturnStatus::Requested) {
            return;
        }

        $request->update([
            'status' => ReturnStatus::Disputed,
            'seller_response' => trim($this->disputeReason),
            'escalated_at' => now(),
        ]);

        $this->refreshSubOrder();

        $this->dispatch('toast', message: __('Return disputed — our team will review it and decide.'));
    }

    protected function afterShipped(SubOrder $subOrder): void
    {
        $this->refreshSubOrder();
    }

    public function render()
    {
        return view('livewire.seller.orders.detail', [
            'cancellationReasons' => CancellationReason::query()->active()->get(),
            'couriers' => self::COURIERS,
            // "5.00" → "5", "5.50" → "5.5" for the commission line.
            'commissionRate' => rtrim(rtrim(number_format((float) $this->subOrder->commission_rate, 2, '.', ''), '0'), '.'),
        ])->title($this->subOrder->sub_order_no);
    }

    private function refreshSubOrder(): void
    {
        $this->subOrder = $this->subOrder->fresh(['items.product.media', 'order.user', 'statusHistories', 'returnRequest.reason', 'returnRequest.media']);
        $this->cancelReasonId = null;
        $this->disputeReason = '';
        $this->disputing = false;
    }
}
