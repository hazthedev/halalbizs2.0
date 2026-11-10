<?php

namespace App\Livewire\Seller\Orders\Concerns;

use App\Models\SubOrder;
use App\Services\ShipmentService;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Shared "Arrange shipment" modal (docs/07 §B) — used by both the order
 * queue quick action and the detail page. The write itself (tracking
 * columns, then the status change through SubOrderStatusService only —
 * CLAUDE.md hard rule 2) lives in ShipmentService, shared with the API.
 */
trait ManagesShipment
{
    /** Hardcoded v1 courier list — moves to shop settings in M6. */
    public const COURIERS = ShipmentService::COURIERS;

    public ?int $shippingSubOrderId = null;

    public string $courier = '';

    public string $courierOther = '';

    public string $trackingNo = '';

    public function openShipModal(int $subOrderId): void
    {
        // Store-scoped lookup — never trust ids from the client.
        $subOrder = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($subOrderId);

        $this->reset('courier', 'courierOther', 'trackingNo');
        $this->resetErrorBag();

        $this->shippingSubOrderId = $subOrder->id;
    }

    public function closeShipModal(): void
    {
        $this->shippingSubOrderId = null;
    }

    public function ship(): void
    {
        $this->validate(
            [
                'courier' => ['required', 'string', Rule::in([...self::COURIERS, 'other'])],
                'courierOther' => ['exclude_unless:courier,other', 'required', 'string', 'max:60'],
                'trackingNo' => ['required', 'string', 'min:6', 'max:64'],
            ],
            [],
            [
                'courier' => __('courier'),
                'courierOther' => __('courier name'),
                'trackingNo' => __('tracking number'),
            ],
        );

        $subOrder = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail((int) $this->shippingSubOrderId);

        try {
            $shipped = app(ShipmentService::class)->ship($subOrder, $this->courier, $this->courierOther, $this->trackingNo, auth()->id());
        } catch (InvalidArgumentException $e) {
            $this->closeShipModal();
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->closeShipModal();
        $this->afterShipped($shipped);

        $this->dispatch('toast', message: __(':no marked as shipped.', ['no' => $subOrder->sub_order_no]));
    }

    /** Hook for the host component (detail refreshes its model). */
    protected function afterShipped(SubOrder $subOrder): void {}
}
