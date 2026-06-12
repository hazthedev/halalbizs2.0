<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\SubOrderStatus;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\SubOrder;
use App\Services\Turnstile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Per-sub-order review panel embedded in each Completed card on the orders
 * list (docs/09 §C) — no route of its own. The only-purchased gate is
 * structural: a review hangs off the buyer's own order_item (unique index),
 * and submit re-checks ownership + completed status + no existing review.
 */
class ReviewOrder extends Component
{
    use WithFileUploads;

    public SubOrder $subOrder;

    public bool $open = false;

    /** @var array<int, int|string|null> rating per order_item_id */
    public array $ratings = [];

    /** @var array<int, string> comment per order_item_id */
    public array $comments = [];

    /** @var array<int, array<int, TemporaryUploadedFile>> */
    public array $photos = [];

    /**
     * Optional seller SERVICE rating — one per sub-order, not per item. The
     * star row renders once per sub-order panel and the value is saved onto
     * the FIRST review submitted for this sub-order (so it never counts
     * twice in the store's service aggregates).
     */
    public int|string|null $sellerRating = null;

    public string $sellerComment = '';

    public ?string $turnstileToken = null;

    public function mount(SubOrder $subOrder): void
    {
        abort_unless($subOrder->order->user_id === auth()->id(), 403);

        $this->subOrder = $subOrder;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function submit(int $orderItemId): void
    {
        abort_unless($this->subOrder->order->user_id === auth()->id(), 403);

        $item = $this->subOrder->items()->with('review')->findOrFail($orderItemId);

        if ($this->subOrder->status !== SubOrderStatus::Completed) {
            $this->dispatch('toast', message: __('You can rate this order once it is completed.'), type: 'error');

            return;
        }

        if ($item->review !== null) {
            $this->dispatch('toast', message: __('You already reviewed this item.'), type: 'error');

            return;
        }

        if ($item->product_id === null) {
            $this->dispatch('toast', message: __('This product can no longer be reviewed.'), type: 'error');

            return;
        }

        $key = 'review-submit:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('toast', message: __('Slow down — you can post another review in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]), type: 'error');

            return;
        }

        $this->validate([
            "ratings.$orderItemId" => ['required', 'integer', 'between:1,5'],
            "comments.$orderItemId" => ['nullable', 'string', 'min:10', 'max:2000'],
            "photos.$orderItemId" => ['nullable', 'array', 'max:5'],
            "photos.$orderItemId.*" => ['image', 'max:4096'],
            'sellerRating' => ['nullable', 'integer', 'between:1,5'],
            'sellerComment' => ['nullable', 'string', 'max:500'],
        ], [
            "ratings.$orderItemId.required" => __('Tap a star to rate this item.'),
            "ratings.$orderItemId.between" => __('Tap a star to rate this item.'),
            "comments.$orderItemId.min" => __('Tell us a little more — at least 10 characters.'),
            "photos.$orderItemId.max" => __('Up to 5 photos per review.'),
            "photos.$orderItemId.*.image" => __('Photos must be images (JPG, PNG or WebP).'),
            "photos.$orderItemId.*.max" => __('Each photo must be 4MB or smaller.'),
            'sellerRating.between' => __('Tap a star to rate the seller.'),
        ]);

        if (! app(Turnstile::class)->verify($this->turnstileToken, request()->ip())) {
            $this->addError('turnstileToken', __('We couldn\'t verify you\'re human — refresh the page and try again.'));

            return;
        }

        $comment = trim($this->comments[$orderItemId] ?? '');

        // The seller service rating attaches to the FIRST review of this
        // sub-order only — later item reviews must not duplicate it.
        $sellerRating = $this->sellerRatingForThisSubmit();
        $sellerComment = trim($this->sellerComment);

        $review = Review::create([
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'store_id' => $this->subOrder->store_id,
            'user_id' => auth()->id(),
            'rating' => (int) $this->ratings[$orderItemId],
            'comment' => $comment !== '' ? $comment : null,
            'seller_rating' => $sellerRating,
            'seller_comment' => $sellerRating !== null && $sellerComment !== '' ? $sellerComment : null,
        ]);

        if ($sellerRating !== null) {
            $this->reset('sellerRating', 'sellerComment');
        }

        foreach ($this->photos[$orderItemId] ?? [] as $photo) {
            $review->addMedia($photo->getRealPath())
                ->usingFileName($photo->getClientOriginalName())
                ->toMediaCollection('photos');
        }

        RateLimiter::hit($key, 60);

        unset($this->ratings[$orderItemId], $this->comments[$orderItemId], $this->photos[$orderItemId]);

        $this->dispatch('toast', message: __('Review posted — thank you!'));
    }

    public function render()
    {
        $items = $this->subOrder->items()->with(['review', 'product.media'])->get();

        return view('livewire.storefront.account.review-order', [
            'items' => $items,
            'pendingCount' => $items->filter(fn (OrderItem $item) => $item->review === null)->count(),
            'sellerRated' => $this->sellerAlreadyRated(),
        ]);
    }

    /** Value to store on THIS review — null when empty or already rated. */
    private function sellerRatingForThisSubmit(): ?int
    {
        if ($this->sellerRating === null || $this->sellerRating === '' || $this->sellerAlreadyRated()) {
            return null;
        }

        return (int) $this->sellerRating;
    }

    /** One service rating per sub-order: does any of its reviews carry one? */
    private function sellerAlreadyRated(): bool
    {
        return Review::query()
            ->whereIn('order_item_id', $this->subOrder->items()->select('id'))
            ->whereNotNull('seller_rating')
            ->exists();
    }
}
