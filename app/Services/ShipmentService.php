<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Jobs\RegisterAfterShipTracking;
use App\Models\SubOrder;
use InvalidArgumentException;

/**
 * "Arrange shipment" (docs/07 §B), shared by the seller-panel modal and
 * POST /api/v1/seller/orders/{id}/ship. Tracking fields are plain columns
 * (forceFill is fine); the status change itself goes ONLY through
 * SubOrderStatusService (CLAUDE.md hard rule 2).
 */
class ShipmentService
{
    /** Hardcoded v1 courier list — moves to shop settings in M6. */
    public const COURIERS = ['J&T Express', 'Pos Laju', 'DHL eCommerce', 'Ninja Van', 'City-Link', 'GDEX'];

    public function __construct(
        private SubOrderStatusService $statusService,
        private EasyParcelService $easyParcel,
    ) {}

    /**
     * @param  string  $courier  One of COURIERS, or 'other' with $courierOther set.
     *
     * @throws InvalidArgumentException when the sub-order is not in `processing`.
     */
    public function ship(SubOrder $subOrder, string $courier, ?string $courierOther, string $trackingNo, int $actorId): SubOrder
    {
        if ($subOrder->status !== SubOrderStatus::Processing) {
            throw new InvalidArgumentException(__('This order can no longer be shipped from its current status.'));
        }

        // Tracking fields only — never the status.
        $subOrder->forceFill([
            'tracking_courier' => $courier === 'other' ? trim((string) $courierOther) : $courier,
            'tracking_no' => trim($trackingNo),
        ])->save();

        $this->statusService->transition($subOrder, SubOrderStatus::Shipped, ActorType::Seller, $actorId);

        // Book a real courier shipment when the store uses EasyParcel (no-op
        // otherwise) — augments the manual tracking with an AWB + label.
        $this->easyParcel->bookIfEnabled($subOrder);

        // Disabled/unkeyed by default. Once AfterShip credentials are added,
        // registration happens off-request and works for any courier/country.
        RegisterAfterShipTracking::dispatch($subOrder->id)->afterCommit();

        return $subOrder->fresh();
    }
}
