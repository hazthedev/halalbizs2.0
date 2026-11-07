<?php

namespace App\Services;

use App\Enums\SubOrderStatus;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use App\Support\UploadName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buyer product reviews (docs/09 §C). The only-purchased gate is structural:
 * a review hangs off the buyer's own order_item (unique index), and submit()
 * re-checks ownership + completed status + no existing review. Product and
 * store rating aggregates follow from ReviewObserver on create.
 */
class ReviewService
{
    /**
     * Write one review per item, with photos, in one transaction. The optional
     * seller SERVICE rating is one per sub-order: it lands on the FIRST review
     * written for the sub-order and is ignored once any review carries one.
     *
     * @param  list<array{order_item_id: int, rating: int, body?: ?string, photos?: list<UploadedFile>}>  $items
     *
     * @throws AuthorizationException when the sub-order is not the buyer's
     * @throws InvalidArgumentException when an item cannot be reviewed
     */
    public function submit(SubOrder $subOrder, User $buyer, array $items, ?int $sellerRating = null, ?string $sellerComment = null): void
    {
        if ($subOrder->order->user_id !== $buyer->id) {
            throw new AuthorizationException;
        }

        if ($subOrder->status !== SubOrderStatus::Completed) {
            throw new InvalidArgumentException(__('You can rate this order once it is completed.'));
        }

        if ($sellerRating !== null && ($sellerRating < 1 || $sellerRating > 5)) {
            throw new InvalidArgumentException(__('Tap a star to rate the seller.'));
        }

        DB::transaction(function () use ($subOrder, $buyer, $items, $sellerRating, $sellerComment) {
            if ($this->sellerRated($subOrder)) {
                $sellerRating = null;
            }

            foreach ($items as $input) {
                $item = $subOrder->items()->with('review')->findOrFail((int) $input['order_item_id']);
                $rating = (int) $input['rating'];

                if ($item->review !== null) {
                    throw new InvalidArgumentException(__('You already reviewed this item.'));
                }

                if ($item->product_id === null) {
                    throw new InvalidArgumentException(__('This product can no longer be reviewed.'));
                }

                if ($rating < 1 || $rating > 5) {
                    throw new InvalidArgumentException(__('Tap a star to rate this item.'));
                }

                $comment = trim((string) ($input['body'] ?? ''));
                $sellerNote = trim((string) $sellerComment);

                $review = Review::create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'store_id' => $subOrder->store_id,
                    'user_id' => $buyer->id,
                    'rating' => $rating,
                    'comment' => $comment !== '' ? $comment : null,
                    'seller_rating' => $sellerRating,
                    'seller_comment' => $sellerRating !== null && $sellerNote !== '' ? $sellerNote : null,
                ]);

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
}
