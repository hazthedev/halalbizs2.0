<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\OrderSettings;
use App\Support\UploadName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

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
     * to return_requested in one transaction, under a lock on the sub-order
     * so two submits cannot both pass the one-request-per-sub-order guard.
     * The status service fires the event that notifies the seller.
     *
     * @param  list<UploadedFile>  $photos  images, at most ReturnRequest::MAX_PHOTOS, 2MB each
     *
     * @throws AuthorizationException when the sub-order is not the buyer's
     * @throws InvalidArgumentException when the sub-order is not eligible or a photo is refused
     */
    public function request(SubOrder $subOrder, User $buyer, ReturnReason $reason, ?string $note, array $photos = []): ReturnRequest
    {
        if ($subOrder->order->user_id !== $buyer->id) {
            throw new AuthorizationException;
        }

        if (! $this->canRequest($subOrder)) {
            throw new InvalidArgumentException(__('This order is no longer eligible for a return.'));
        }

        $this->validatePhotos($photos);

        $note = trim((string) $note);

        return DB::transaction(function () use ($subOrder, $buyer, $reason, $note, $photos) {
            $locked = SubOrder::whereKey($subOrder->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canRequest($locked)) {
                throw new InvalidArgumentException(__('This order is no longer eligible for a return.'));
            }

            try {
                $request = ReturnRequest::create([
                    'sub_order_id' => $locked->id,
                    'return_reason_id' => $reason->id,
                    'description' => $note !== '' ? $note : null,
                    'status' => ReturnStatus::Requested,
                    'respond_by' => now()->addHours($this->settings->return_seller_response_hours),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new InvalidArgumentException(__('This order is no longer eligible for a return.'));
            }

            foreach ($photos as $photo) {
                $request->addMedia($photo->getRealPath())
                    ->usingFileName(UploadName::safe($photo, 'return-'))
                    ->toMediaCollection('photos');
            }

            $this->statusService->transition($locked, SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);

            return $request;
        });
    }

    /**
     * The same photo rules the web form enforces, so an API caller cannot
     * hand addMedia() a non-image.
     *
     * @param  list<UploadedFile>  $photos
     */
    private function validatePhotos(array $photos): void
    {
        $validator = Validator::make(['photos' => $photos], [
            'photos' => ['array', 'max:'.ReturnRequest::MAX_PHOTOS],
            'photos.*' => ['image', 'max:2048'],
        ], [
            'photos.max' => __('You can attach up to :max photos.', ['max' => ReturnRequest::MAX_PHOTOS]),
            'photos.*.image' => __('Photos must be images (JPG, PNG or WebP).'),
            'photos.*.max' => __('Each photo must be 2MB or smaller.'),
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }
    }
}
