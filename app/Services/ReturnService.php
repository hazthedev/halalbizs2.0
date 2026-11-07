<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\BuyerActionException;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\OrderSettings;
use App\Support\UploadName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Buyer return requests (docs/09 §D): one request per sub-order, inside the
 * return window, created together with the return_requested transition. The
 * web order page and the API both call request().
 */
class ReturnService
{
    public function __construct(
        private SubOrderStatusService $statusService,
        private OrderSettings $settings,
    ) {}

    /**
     * Delivered/completed, inside the return window, and no request yet
     * (one return request per sub-order — docs/09 §D).
     */
    public function canRequest(SubOrder $subOrder): bool
    {
        return in_array($subOrder->status, [SubOrderStatus::Delivered, SubOrderStatus::Completed], true)
            && $subOrder->delivered_at !== null
            && now()->lte($subOrder->delivered_at->copy()->addDays($this->settings->return_window_days))
            && $subOrder->returnRequest === null;
    }

    /**
     * Create the request, attach the evidence photos and move the sub-order
     * to return_requested in one transaction. The status service fires the
     * event that notifies the seller.
     *
     * @param  list<UploadedFile>  $photos  at most ReturnRequest::MAX_PHOTOS are kept
     *
     * @throws AuthorizationException when the sub-order is not the buyer's
     * @throws BuyerActionException when the sub-order is not eligible
     */
    public function request(SubOrder $subOrder, User $buyer, ReturnReason $reason, ?string $note, array $photos = []): ReturnRequest
    {
        if ($subOrder->order->user_id !== $buyer->id) {
            throw new AuthorizationException;
        }

        if (! $this->canRequest($subOrder)) {
            throw new BuyerActionException(__('This order is no longer eligible for a return.'));
        }

        $note = trim((string) $note);

        return DB::transaction(function () use ($subOrder, $buyer, $reason, $note, $photos) {
            $request = ReturnRequest::create([
                'sub_order_id' => $subOrder->id,
                'return_reason_id' => $reason->id,
                'description' => $note !== '' ? $note : null,
                'status' => ReturnStatus::Requested,
                'respond_by' => now()->addHours($this->settings->return_seller_response_hours),
            ]);

            foreach (array_slice($photos, 0, ReturnRequest::MAX_PHOTOS) as $photo) {
                $request->addMedia($photo->getRealPath())
                    ->usingFileName(UploadName::safe($photo, 'return-'))
                    ->toMediaCollection('photos');
            }

            $this->statusService->transition($subOrder, SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);

            return $request;
        });
    }
}
