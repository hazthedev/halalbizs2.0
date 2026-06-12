<?php

namespace App\Livewire\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Notifications\ProductModerationNotification;
use App\Settings\ModerationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Moderation queue (docs/08 §D) — pending_review products with per-row and
 * bulk Approve / Reject (reason required) / Ban, plus a banned reference
 * list. Every outcome notifies the store owner (database + mail).
 */
#[Layout('layouts.admin')]
class Moderation extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    public const BANNED_LIMIT = 50;

    /** @var array<int, string> selected pending product ids */
    public array $selected = [];

    public bool $selectPage = false;

    // ── Reject modal ───────────────────────────────────────────────────
    public bool $rejectOpen = false;

    /** @var array<int, int> products the reject reason applies to */
    public array $rejectIds = [];

    public string $rejectReason = '';

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->pendingQuery()->paginate(self::PER_PAGE)->pluck('id')->map(fn (int $id) => (string) $id)->all()
            : [];
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    // ── Approve ────────────────────────────────────────────────────────

    public function approve(int $productId): void
    {
        $count = $this->approveProducts($this->pendingByIds([$productId]));

        $this->dispatch('toast', message: $count > 0 ? __('Product approved — it is now live.') : __('Nothing to approve.'));
    }

    public function bulkApprove(): void
    {
        $count = $this->approveProducts($this->pendingByIds(array_map(intval(...), $this->selected)));
        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{0}Nothing to approve.|{1}:count product approved|[2,*]:count products approved', $count, ['count' => $count]));
    }

    // ── Reject (reason required) ───────────────────────────────────────

    public function startReject(int $productId): void
    {
        $this->openRejectModal([$productId]);
    }

    public function startBulkReject(): void
    {
        $ids = array_map(intval(...), $this->selected);

        if ($ids === []) {
            return;
        }

        $this->openRejectModal($ids);
    }

    public function confirmReject(): void
    {
        $this->validate(
            ['rejectReason' => ['required', 'string', 'min:3', 'max:1000']],
            messages: ['rejectReason.required' => __('Tell the seller what to fix — the reason goes into their notification.')],
            attributes: ['rejectReason' => __('reason')],
        );

        $reason = trim($this->rejectReason);
        $count = 0;

        foreach ($this->pendingByIds($this->rejectIds) as $product) {
            $product->update(['status' => ProductStatus::Draft]);
            $product->store?->user?->notify(new ProductModerationNotification($product, 'rejected', $reason));
            $count++;
        }

        $this->cancelReject();
        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{0}Nothing to reject.|{1}:count product rejected — the seller has been notified.|[2,*]:count products rejected — the sellers have been notified.', $count, ['count' => $count]));
    }

    public function cancelReject(): void
    {
        $this->reset(['rejectOpen', 'rejectIds', 'rejectReason']);
        $this->resetErrorBag();
    }

    // ── Ban ────────────────────────────────────────────────────────────

    public function ban(int $productId): void
    {
        $count = $this->banProducts($this->pendingByIds([$productId]));

        $this->dispatch('toast', message: $count > 0 ? __('Product banned — the seller has been notified.') : __('Nothing to ban.'));
    }

    public function bulkBan(): void
    {
        $count = $this->banProducts($this->pendingByIds(array_map(intval(...), $this->selected)));
        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{0}Nothing to ban.|{1}:count product banned|[2,*]:count products banned', $count, ['count' => $count]));
    }

    public function render()
    {
        return view('livewire.admin.catalog.moderation', [
            'requireApproval' => app(ModerationSettings::class)->require_product_approval,
            'pending' => $this->pendingQuery()->paginate(self::PER_PAGE),
            'banned' => Product::query()
                ->where('status', ProductStatus::Banned)
                ->with(['store', 'category', 'media', 'variants'])
                ->latest('updated_at')
                ->take(self::BANNED_LIMIT)
                ->get(),
        ])->title(__('Moderation'));
    }

    private function pendingQuery(): Builder
    {
        return Product::query()
            ->where('status', ProductStatus::PendingReview)
            ->with(['store', 'category', 'media', 'variants'])
            ->oldest('updated_at')
            ->oldest('id');
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    private function pendingByIds(array $ids): Collection
    {
        return Product::query()
            ->where('status', ProductStatus::PendingReview)
            ->whereIn('id', $ids)
            ->with('store.user')
            ->get();
    }

    /** @param  Collection<int, Product>  $products */
    private function approveProducts(Collection $products): int
    {
        foreach ($products as $product) {
            $product->status = ProductStatus::Live;

            if ($product->published_at === null) {
                $product->published_at = now();
            }

            $product->save();

            $product->store?->user?->notify(new ProductModerationNotification($product, 'approved'));
        }

        return $products->count();
    }

    /** @param  Collection<int, Product>  $products */
    private function banProducts(Collection $products): int
    {
        foreach ($products as $product) {
            $product->update(['status' => ProductStatus::Banned]);
            $product->store?->user?->notify(new ProductModerationNotification($product, 'banned'));
        }

        return $products->count();
    }

    /** @param  array<int, int>  $ids */
    private function openRejectModal(array $ids): void
    {
        $this->rejectIds = $ids;
        $this->rejectReason = '';
        $this->rejectOpen = true;
        $this->resetErrorBag();
    }

    private function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }
}
