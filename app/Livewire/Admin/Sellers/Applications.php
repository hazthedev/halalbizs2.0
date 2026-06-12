<?php

namespace App\Livewire\Admin\Sellers;

use App\Enums\StoreStatus;
use App\Livewire\Admin\Sellers\Concerns\ReviewsDocuments;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Notifications\SellerApplicationDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seller applications queue (docs/08 §B) — pending stores, oldest first,
 * with an inline review panel: full details, bank info, document
 * verification, then approve (role + email) or reject (reason + email).
 * Store status changes are activity-logged via the model's LogsActivity.
 */
#[Layout('layouts.admin')]
class Applications extends Component
{
    use ReviewsDocuments, WithPagination;

    public const PER_PAGE = 15;

    /** Store id whose review panel is open. */
    public ?int $reviewing = null;

    public string $rejectionReason = '';

    public function review(int $storeId): void
    {
        $this->reviewing = $this->reviewing === $storeId ? null : $storeId;
        $this->rejectionReason = '';
        $this->docNotes = [];
        $this->resetErrorBag();
    }

    public function approve(int $storeId): void
    {
        $store = $this->pending()->with('user')->findOrFail($storeId);

        DB::transaction(function () use ($store) {
            $store->update([
                'status' => StoreStatus::Approved,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $store->user->assignRole('seller');
        });

        $store->user->notify(new SellerApplicationDecision($store, 'approved'));

        $this->reviewing = null;
        $this->dispatch('toast', message: __(':store approved — the owner now has seller access.', ['store' => $store->name]));
    }

    public function reject(int $storeId): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'rejectionReason.required' => __('Give the applicant a reason — it is emailed to them.'),
            'rejectionReason.min' => __('Give the applicant a reason — it is emailed to them.'),
        ]);

        $store = $this->pending()->with('user')->findOrFail($storeId);

        $store->update([
            'status' => StoreStatus::Rejected,
            'rejection_reason' => $this->rejectionReason,
        ]);

        $store->user->notify(new SellerApplicationDecision($store, 'rejected', $this->rejectionReason));

        $this->reviewing = null;
        $this->rejectionReason = '';
        $this->dispatch('toast', message: __(':store rejected — the applicant has been notified.', ['store' => $store->name]));
    }

    protected function reviewableDocuments(): Builder
    {
        // Only documents of stores still in the queue.
        return StoreDocument::query()->whereHas(
            'store',
            fn (Builder $query) => $query->where('status', StoreStatus::Pending),
        );
    }

    private function pending(): Builder
    {
        return Store::query()->where('status', StoreStatus::Pending);
    }

    public function render()
    {
        $applications = $this->pending()
            ->with('user')
            ->withCount('documents')
            ->oldest('created_at')
            ->oldest('id')
            ->paginate(self::PER_PAGE);

        $reviewingStore = $this->reviewing !== null
            ? $this->pending()->with(['user', 'documents.media'])->find($this->reviewing)
            : null;

        return view('livewire.admin.sellers.applications', [
            'applications' => $applications,
            'reviewingStore' => $reviewingStore,
        ])->title(__('Seller applications'));
    }
}
