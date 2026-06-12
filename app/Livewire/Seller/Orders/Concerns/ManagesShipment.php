<?php

namespace App\Livewire\Seller\Orders\Concerns;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Services\SubOrderStatusService;
use Illuminate\Validation\Rule;

/**
 * Shared "Arrange shipment" modal (docs/07 §B) — used by both the order
 * queue quick action and the detail page. Tracking fields are plain
 * columns (forceFill is fine); the status change itself goes ONLY
 * through SubOrderStatusService (CLAUDE.md hard rule 2).
 */
trait ManagesShipment
{
    /** Hardcoded v1 courier list — moves to shop settings in M6. */
    public const COURIERS = ['J&T Express', 'Pos Laju', 'DHL eCommerce', 'Ninja Van', 'City-Link', 'GDEX'];

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

        if ($subOrder->status !== SubOrderStatus::Processing) {
            $this->closeShipModal();
            $this->dispatch('toast', message: __('This order can no longer be shipped from its current status.'), type: 'error');

            return;
        }

        // Tracking fields only — never the status.
        $subOrder->forceFill([
            'tracking_courier' => $this->courier === 'other' ? trim($this->courierOther) : $this->courier,
            'tracking_no' => trim($this->trackingNo),
        ])->save();

        app(SubOrderStatusService::class)->transition($subOrder, SubOrderStatus::Shipped, ActorType::Seller, auth()->id());

        $this->closeShipModal();
        $this->afterShipped($subOrder);

        $this->dispatch('toast', message: __(':no marked as shipped.', ['no' => $subOrder->sub_order_no]));
    }

    /** Hook for the host component (detail refreshes its model). */
    protected function afterShipped(SubOrder $subOrder): void {}
}
