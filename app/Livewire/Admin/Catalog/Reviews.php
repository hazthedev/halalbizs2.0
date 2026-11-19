<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Review;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithTable;

/**
 * Review moderation (docs/09 §C) — every review on the marketplace, with
 * hide/unhide behind a mandatory reason that lands in the activity log.
 * ReviewObserver recalculates the cached product/store aggregates whenever
 * is_hidden flips.
 */
#[Layout('layouts.admin')]
class Reviews extends Component
{
    use WithTable;

    public const PER_PAGE = 15;

    #[Url(except: '')]
    public string $rating = '';

    #[Url(except: false)]
    public bool $hiddenOnly = false;

    public ?int $moderatingId = null;

    public string $moderationReason = '';

    protected function tableFilters(): array
    {
        return ['rating', 'hiddenOnly'];
    }

    protected function tableSorts(): array
    {
        return ['rating' => 'rating', 'posted' => 'created_at'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'rating', 'hiddenOnly');
        $this->resetPage();
    }

    public function startModeration(int $reviewId): void
    {
        $this->moderatingId = Review::query()->findOrFail($reviewId)->id;
        $this->moderationReason = '';
        $this->resetErrorBag();
    }

    public function cancelModeration(): void
    {
        $this->reset('moderatingId', 'moderationReason');
        $this->resetErrorBag();
    }

    public function confirmModeration(): void
    {
        if ($this->moderatingId === null) {
            return;
        }

        $this->validate([
            'moderationReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'moderationReason.required' => __('Give a reason — it is kept in the audit log.'),
            'moderationReason.min' => __('Give a reason — it is kept in the audit log.'),
        ]);

        $review = Review::query()->findOrFail($this->moderatingId);

        $hide = ! $review->is_hidden;

        // ReviewObserver recalculates product + store aggregates on this flip.
        $review->update(['is_hidden' => $hide]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($review)
            ->withProperties(['reason' => $this->moderationReason])
            ->log($hide ? 'review.hidden' : 'review.unhidden');

        $this->cancelModeration();
        $this->dispatch('toast', message: $hide
            ? __('Review hidden — it no longer counts toward ratings.')
            : __('Review restored — it counts toward ratings again.'));
    }

    public function render()
    {
        $search = trim($this->search);

        $reviews = Review::query()
                ->with(['product', 'store', 'user'])
                ->when($this->hiddenOnly, fn ($query) => $query->where('is_hidden', true))
                ->when(in_array($this->rating, ['1', '2', '3', '4', '5'], true), fn ($query) => $query->where('rating', (int) $this->rating))
                ->when($search !== '', fn ($query) => $query->whereHas(
                    'store', fn ($storeQuery) => $storeQuery->where('name', 'like', $this->likeTerm())
                ))
                ;

        return view('livewire.admin.catalog.reviews', [
            'reviews' => $this->sortTable($reviews)->paginate($this->tablePageSize()),
            'moderating' => $this->moderatingId !== null
                ? Review::query()->with('product')->find($this->moderatingId)
                : null,
        ])->title(__('Reviews'));
    }
}
