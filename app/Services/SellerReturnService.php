<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use InvalidArgumentException;

/**
 * The seller's three moves on a return request (docs/09 §D), shared by the
 * seller order detail page and the mobile API. Accept and dispute only touch
 * the request row; the sub-order moves to `returned` solely through
 * SubOrderStatusService once the accepted item is back (hard rule 2).
 */
class SellerReturnService
{
    public function __construct(private SubOrderStatusService $statusService) {}

    /**
     * Step 1 of the accept path: the request flips to accepted and the buyer
     * ships the item back (manual v1). The sub-order stays return_requested
     * until the seller confirms receipt.
     *
     * @throws InvalidArgumentException when there is no open request to accept.
     */
    public function accept(SubOrder $subOrder): ReturnRequest
    {
        $request = $this->openRequest($subOrder, ReturnStatus::Requested);

        $request->update(['status' => ReturnStatus::Accepted]);

        return $request;
    }

    /**
     * Dispute → straight to the admin queue. The sub-order status does not move.
     *
     * @throws InvalidArgumentException when there is no open request to dispute.
     */
    public function dispute(SubOrder $subOrder, string $reason): ReturnRequest
    {
        $request = $this->openRequest($subOrder, ReturnStatus::Requested);

        $request->update([
            'status' => ReturnStatus::Disputed,
            'seller_response' => trim($reason),
            'escalated_at' => now(),
        ]);

        return $request;
    }

    /**
     * Step 2: item came back → returned. The refund itself is an admin task.
     *
     * @throws InvalidArgumentException unless the request was accepted first.
     */
    public function received(SubOrder $subOrder, int $actorId): SubOrder
    {
        $this->openRequest($subOrder, ReturnStatus::Accepted);

        return $this->statusService->transition(
            $subOrder,
            SubOrderStatus::Returned,
            ActorType::Seller,
            $actorId,
            __('Returned item received by the seller'),
        );
    }

    private function openRequest(SubOrder $subOrder, ReturnStatus $expected): ReturnRequest
    {
        $request = $subOrder->returnRequest;

        if ($subOrder->status !== SubOrderStatus::ReturnRequested || $request?->status !== $expected) {
            throw new InvalidArgumentException(__('This return request cannot be updated from its current state.'));
        }

        return $request;
    }
}
