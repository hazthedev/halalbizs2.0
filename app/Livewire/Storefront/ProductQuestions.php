<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Notifications\ProductQuestionAsked;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * PDP "Questions & Answers" tab, lazy-loaded like the reviews island. Buyers
 * ask public questions; the seller answers from the seller centre. Only
 * non-hidden questions are listed; answered ones show the seller's reply.
 */
#[Lazy]
class ProductQuestions extends Component
{
    /**
     * Hard ceiling on $limit (AL-M5): loadMore() legitimately increments it,
     * so it can't be #[Locked] — but it's fully client-mutable via
     * $wire.set, so clamp wherever it feeds a query.
     */
    private const MAX_LIMIT = 50;

    public Product $product;

    public string $question = '';

    public int $limit = 5;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function loadMore(): void
    {
        $this->limit += 5;
    }

    public function ask(): void
    {
        if (auth()->guest()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $key = 'ask-question:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('toast', message: __('Slow down — you can ask again in :s seconds.', [
                's' => RateLimiter::availableIn($key),
            ]), type: 'error');

            return;
        }

        $this->validate([
            'question' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'question.required' => __('Type your question first.'),
            'question.min' => __('Add a little more detail (at least 10 characters).'),
        ]);

        RateLimiter::hit($key, 60);

        $this->product->questions()->create([
            'store_id' => $this->product->store_id,
            'user_id' => auth()->id(),
            'question' => trim($this->question),
        ]);

        $this->product->store?->user?->notify(new ProductQuestionAsked($this->product));

        $this->reset('question');
        $this->dispatch('toast', message: __("Question sent — we'll notify you when the seller answers."));
    }

    public function placeholder(): View
    {
        return view('livewire.storefront.partials.product-reviews-placeholder');
    }

    public function render()
    {
        $base = $this->product->questions()->visible();
        $limit = $this->clampedLimit();

        return view('livewire.storefront.product-questions', [
            'questions' => (clone $base)->with(['user', 'answerer'])->latest('id')->take($limit)->get(),
            'totalCount' => (clone $base)->count(),
            'hasMore' => (clone $base)->count() > $limit,
        ]);
    }

    /** $limit clamped to a sane ceiling (AL-M5), whatever the client set it to. */
    private function clampedLimit(): int
    {
        return max(0, min($this->limit, self::MAX_LIMIT));
    }
}
