<?php

namespace App\Livewire\Seller\Orders;

use App\Enums\ActorType;
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

    public function mount(SubOrder $subOrder): void
    {
        // Leakage guard: another store's sub-order is a 403, not a redirect.
        $this->authorizeStore($subOrder->store_id);

        $this->subOrder = $subOrder->load(['items.product.media', 'order.user', 'statusHistories']);
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
        $this->subOrder = $this->subOrder->fresh(['items.product.media', 'order.user', 'statusHistories']);
        $this->cancelReasonId = null;
    }
}
