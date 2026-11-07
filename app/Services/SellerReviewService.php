<?php

namespace App\Services;

use App\Models\Review;
use InvalidArgumentException;

/**
 * ONE seller reply per review (docs/09 §C). A reply stays editable for 24
 * hours after it is first posted, then locks — the window never resets on
 * edit. Shared by the seller review inbox and the mobile API.
 */
class SellerReviewService
{
    /** @throws InvalidArgumentException when the 24h edit window has closed. */
    public function reply(Review $review, string $body): Review
    {
        if ($review->replyLocked()) {
            throw new InvalidArgumentException(__('Replies lock 24 hours after posting.'));
        }

        $review->update([
            'seller_reply' => trim($body),
            // First reply starts the 24h edit window; edits never reset it.
            'seller_replied_at' => $review->seller_replied_at ?? now(),
        ]);

        return $review;
    }
}
