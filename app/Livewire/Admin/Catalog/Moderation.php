<?php

namespace App\Livewire\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Notifications\ProductModerationNotification;
use App\Services\ProductPublishPolicy;
use App\Settings\ModerationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Livewire\Concerns\WithTable;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\DisplayTime;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Moderation queue (docs/08 §D) — pending_review products with per-row and
 * bulk Approve / Reject (reason required) / Ban, plus a banned reference
 * list. Every outcome notifies the store owner (database + mail).
 */
#[Layout('layouts.admin')]
class Moderation extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    public const BANNED_LIMIT = 50;

    #[Url(except: '')]
    public string $store = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    protected function tableFilters(): array
    {
        return ['store', 'category', 'dateFrom', 'dateTo'];
    }

    protected function tableSorts(): array
    {
        return [
            'submitted' => 'updated_at',
            // ponytail: list price, not the live sale price; sale windows are PHP-side only.
            'price' => ProductVariant::query()->selectRaw('MIN(price_sen)')->whereColumn('product_variants.product_id', 'products.id'),
        ];
    }

    protected function tableDefaultOrder(): array
    {
        return ['updated_at' => 'asc'];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'store', 'category', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    // ── Reject modal ───────────────────────────────────────────────────
    public bool $rejectOpen = false;

    /** @var array<int, int> products the reject reason applies to; server-set from selectedIds(), client cannot rewrite it (HB-G173) */
    #[Locked]
    public array $rejectIds = [];

    public string $rejectReason = '';

    // ── Approve ────────────────────────────────────────────────────────

    public function approve(int $productId): void
    {
        $count = $this->approveProducts($this->pendingByIds([$productId]));

        $this->dispatch('toast', message: $count > 0 ? __('Product approved — it is now live.') : __('Nothing to approve.'));
    }

    public function bulkApprove(): void
    {
        $count = $this->approveProducts($this->pendingByIds($this->selectedIds()));
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
        $ids = $this->selectedIds();

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
        $count = $this->banProducts($this->pendingByIds($this->selectedIds()));
        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{0}Nothing to ban.|{1}:count product banned|[2,*]:count products banned', $count, ['count' => $count]));
    }

    public function render()
    {
        return view('livewire.admin.catalog.moderation', [
            'requireApproval' => app(ModerationSettings::class)->require_product_approval,
            'pending' => $this->results(),
            'stores' => Store::query()->whereHas('products', fn ($q) => $q->where('status', ProductStatus::PendingReview))->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->whereHas('products', fn ($q) => $q->where('status', ProductStatus::PendingReview))->orderBy('id')->get(),
            'banned' => Product::query()
                ->where('status', ProductStatus::Banned)
                ->with(['store', 'category', 'media', 'variants'])
                ->latest('updated_at')
                ->take(self::BANNED_LIMIT)
                ->get(),
        ])->title(__('Moderation'));
    }

    private function results()
    {
        $term = trim($this->search);

        $query = Product::query()
            ->where('status', ProductStatus::PendingReview)
            ->with(['store', 'category', 'media', 'variants'])
            ->when(ctype_digit($this->store), fn (Builder $q) => $q->where('store_id', (int) $this->store))
            ->when(ctype_digit($this->category), fn (Builder $q) => $q->where('category_id', (int) $this->category))
            ->when($this->validDate($this->dateFrom), fn (Builder $q, $d) => $q->where('updated_at', '>=', DisplayTime::dayStart($d)))
            ->when($this->validDate($this->dateTo), fn (Builder $q, $d) => $q->where('updated_at', '<=', DisplayTime::dayEnd($d)))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereRaw(JsonSearch::clause('name'), [JsonSearch::pattern($term)])
                ->orWhereHas('store', fn (Builder $s) => $s->where('name', 'like', '%'.$term.'%'))));

        return $this->sortTable($query)->paginate($this->tablePageSize());
    }

    /** Date inputs arrive as Y-m-d from the browser; ignore anything else. */
    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
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
        $policy = app(ProductPublishPolicy::class);

        foreach ($products as $product) {
            // H-6: moderation approval is a publish. Skipping the gate here
            // would let the one route that BYPASSES the seller's screen also
            // bypass the certificate rule.
            if (! $policy->allows($product)) {
                continue;
            }

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
}
