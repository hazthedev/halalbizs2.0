<?php

namespace App\Livewire\Seller\Reviews;

use App\Livewire\Concerns\CurrentStore;
use App\Models\Review;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Store review inbox (docs/09 §C) — every review on the store's products,
 * with ONE reply per review. A reply stays editable for 24 hours after it
 * is first posted, then locks (the window never resets on edit).
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore, WithPagination;

    public ?int $replyingId = null;

    public string $replyText = '';

    public function startReply(int $reviewId): void
    {
        $review = $this->ownReview($reviewId);

        if ($review->replyLocked()) {
            $this->dispatch('toast', message: __('Replies lock 24 hours after posting.'), type: 'error');

            return;
        }

        $this->replyingId = $review->id;
        $this->replyText = (string) $review->seller_reply;
        $this->resetErrorBag();
    }

    public function cancelReply(): void
    {
        $this->reset('replyingId', 'replyText');
        $this->resetErrorBag();
    }

    public function saveReply(): void
    {
        if ($this->replyingId === null) {
            return;
        }

        $review = $this->ownReview($this->replyingId);

        if ($review->replyLocked()) {
            $this->dispatch('toast', message: __('Replies lock 24 hours after posting.'), type: 'error');
            $this->cancelReply();

            return;
        }

        $this->validate([
            'replyText' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'replyText.required' => __('Write your reply before posting it.'),
            'replyText.min' => __('Write at least 5 characters.'),
        ]);

        $review->update([
            'seller_reply' => trim($this->replyText),
            // First reply starts the 24h edit window; edits never reset it.
            'seller_replied_at' => $review->seller_replied_at ?? now(),
        ]);

        $this->cancelReply();
        $this->dispatch('toast', message: __('Reply posted — buyers see it on the product page.'));
    }

    public function render()
    {
        return view('livewire.seller.reviews.index', [
            'reviews' => Review::query()
                ->where('store_id', $this->currentStore()->id)
                ->with(['product', 'orderItem', 'user', 'media'])
                ->latest()
                ->latest('id')
                ->paginate(10),
        ])->title(__('Reviews'));
    }

    /** Store-scoped lookup — other stores' reviews 404 (never leak). */
    private function ownReview(int $reviewId): Review
    {
        $review = Review::query()
            ->where('store_id', $this->currentStore()->id)
            ->find($reviewId);

        abort_if($review === null, 404);

        return $review;
    }
}
