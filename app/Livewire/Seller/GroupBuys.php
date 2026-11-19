<?php

namespace App\Livewire\Seller;

use App\Enums\GroupBuyStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\GroupBuy;
use App\Support\JsonSearch;
use Illuminate\Contracts\View\View;
use App\Livewire\Concerns\WithTable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller group-buy deals (M2.6). Create a per-variant group price that unlocks
 * when a target number of shoppers team up within the window. Store-scoped via
 * CurrentStore; price stays integer sen (Hard Rule 1).
 */
#[Layout('layouts.seller')]
class GroupBuys extends Component
{
    use CurrentStore, WithTable;

    public const PER_PAGE = 15;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['price' => 'group_price_sen', 'people' => 'target_size', 'teams' => 'teams_count', 'status' => 'status'];
    }

    protected function tableDefaultOrder(): array
    {
        return [];
    }

    public function mount(): void
    {
        abort_unless(config('groupbuy.enabled', true), 404);
    }

    public function end(int $dealId): void
    {
        $deal = GroupBuy::where('id', $dealId)->where('store_id', $this->currentStore()->id)->first();

        $deal?->update(['status' => GroupBuyStatus::Ended]);
        $this->dispatch('toast', message: __('Deal ended.'), type: 'success');
    }

    /** Active deals of THIS store only; foreign or already-ended ids are ignored. */
    public function bulkEnd(): void
    {
        $count = GroupBuy::where('store_id', $this->currentStore()->id)
            ->where('status', GroupBuyStatus::Active)
            ->whereIn('id', array_map(intval(...), $this->selected))
            ->update(['status' => GroupBuyStatus::Ended]);

        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{1}:count deal ended|[2,*]:count deals ended', $count, ['count' => $count]), type: 'success');
    }

    public function render(): View
    {
        $store = $this->currentStore();

        return view('livewire.seller.group-buys', [
            'deals' => $this->sortTable(GroupBuy::where('store_id', $store->id)
                ->with(['variant', 'product'])
                ->withCount('teams')
                ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->whereRaw(JsonSearch::clause('name->en'), [JsonSearch::pattern($this->search)]))))
                ->paginate($this->tablePageSize()),
        ])->title(__('Group buys'));
    }
}
