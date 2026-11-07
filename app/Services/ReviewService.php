<?php

namespace App\Services;

use App\Exceptions\BuyerActionException;
use App\Enums\SubOrderStatus;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use App\Support\UploadName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Buyer product reviews (docs/09 §C). The only-purchased gate is structural:
 * a review hangs off the buyer's own order_item (unique index), and submit()
 * re-checks ownership + completed status + no existing review under a lock
 * on the sub-order. Product and store rating aggregates follow from
 * ReviewObserver on create.
 */
class ReviewService
{
    public const MAX_PHOTOS = 5;

    /**
     * The business gate for one item, without writing anything: the buyer's
     * own completed sub-order, the item still has a product, not reviewed
     * yet. The web panel runs it before spending a Turnstile token.
     *
     * @throws AuthorizationException when the sub-order is not the buyer's
     * @throws BuyerActionException with a buyer-safe reason
     */
    public function assertReviewable(SubOrder $subOrder, User $buyer, int $orderItemId): OrderItem
    {
        if ($subOrder->order->user_id !== $buyer->id) {
            throw new AuthorizationException;
        }

        if ($subOrder->status !== SubOrderStatus::Completed) {
            throw new BuyerActionException(__('You can rate this order once it is completed.'));
        }

        $item = $subOrder->items()->with('review')->findOrFail($orderItemId);

        if ($item->review !== null) {
            throw new BuyerActionException(__('You already reviewed this item.'));
        }

        if ($item->product_id === null) {
            throw new BuyerActionException(__('This product can no longer be reviewed.'));
        }

        return $item;
    }

    /**
     * Write one review per item, with photos, in one transaction under a lock
     * on the sub-order. The optional seller SERVICE rating is one per
     * sub-order: it lands on the FIRST review written for the sub-order and
     * is ignored once any review carries one.
     *
     * @param  list<array{order_item_id: int, rating: int, body?: ?string, photos?: list<UploadedFile>}>  $items
     *
     * @throws AuthorizationException when the sub-order is not the buyer's
     * @throws BuyerActionException when an item cannot be reviewed
     */
    public function submit(SubOrder $subOrder, User $buyer, array $items, ?int $sellerRating = null, ?string $sellerComment = null): void
    {
        if ($subOrder->order->user_id !== $buyer->id) {
            throw new AuthorizationException;
        }

        if ($sellerRating !== null && ($sellerRating < 1 || $sellerRating > 5)) {
            throw new BuyerActionException(__('Tap a star to rate the seller.'));
        }

        foreach ($items as $input) {
            $rating = (int) $input['rating'];

            if ($rating < 1 || $rating > 5) {
                throw new BuyerActionException(__('Tap a star to rate this item.'));
            }

            $this->validatePhotos($input['photos'] ?? []);
        }

        DB::transaction(function () use ($subOrder, $buyer, $items, $sellerRating, $sellerComment) {
            // Serialise concurrent submits for the same sub-order; the guards
            // below re-run against what the lock lets us see.
            $locked = SubOrder::whereKey($subOrder->getKey())->lockForUpdate()->firstOrFail();

            if ($this->sellerRated($locked)) {
                $sellerRating = null;
            }

            foreach ($items as $input) {
                $item = $this->assertReviewable($locked, $buyer, (int) $input['order_item_id']);
                $comment = trim((string) ($input['body'] ?? ''));
                $sellerNote = trim((string) $sellerComment);

                try {
                    $review = Review::create([
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'store_id' => $locked->store_id,
                        'user_id' => $buyer->id,
                        'rating' => (int) $input['rating'],
                        'comment' => $comment !== '' ? $comment : null,
                        'seller_rating' => $sellerRating,
                        'seller_comment' => $sellerRating !== null && $sellerNote !== '' ? $sellerNote : null,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw new BuyerActionException(__('You already reviewed this item.'));
                }

                foreach ($input['photos'] ?? [] as $photo) {
                    $review->addMedia($photo->getRealPath())
                        ->usingFileName(UploadName::safe($photo))
                        ->toMediaCollection('photos');
                }

                // The seller service rating attaches to the FIRST review of this
                // sub-order only — later item reviews must not duplicate it.
                $sellerRating = null;
            }
        });
    }

    /** One service rating per sub-order: does any of its reviews carry one? */
    public function sellerRated(SubOrder $subOrder): bool
    {
        return Review::query()
            ->whereIn('order_item_id', $subOrder->items()->select('id'))
            ->whereNotNull('seller_rating')
            ->exists();
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
            'photos' => ['array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'photos.max' => __('Up to 5 photos per review.'),
            'photos.*.image' => __('Photos must be images (JPG, PNG or WebP).'),
            'photos.*.mimes' => __('Photos must be images (JPG, PNG or WebP).'),
            'photos.*.max' => __('Each photo must be 4MB or smaller.'),
        ]);

        if ($validator->fails()) {
            throw new BuyerActionException($validator->errors()->first());
        }
    }
}
