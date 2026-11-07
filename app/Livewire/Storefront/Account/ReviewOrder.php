<?php

namespace App\Livewire\Storefront\Account;

use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Services\ReviewService;
use App\Services\Turnstile;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
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

    public function submit(int $orderItemId, ReviewService $reviews): void
    {
        abort_unless($this->subOrder->order->user_id === auth()->id(), 403);

        $this->subOrder->items()->findOrFail($orderItemId);

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

        // The seller service rating attaches to the FIRST review of this
        // sub-order only — later item reviews must not duplicate it.
        $sellerRating = $this->sellerRatingForThisSubmit();

        try {
            $reviews->submit($this->subOrder, auth()->user(), [[
                'order_item_id' => $orderItemId,
                'rating' => (int) $this->ratings[$orderItemId],
                'body' => $this->comments[$orderItemId] ?? null,
                'photos' => $this->photos[$orderItemId] ?? [],
            ]], $sellerRating, $this->sellerComment);
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($sellerRating !== null) {
            $this->reset('sellerRating', 'sellerComment');
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
        return app(ReviewService::class)->sellerRated($this->subOrder);
    }
}
