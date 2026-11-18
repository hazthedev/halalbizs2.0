<?php

namespace App\Livewire\Seller;

use App\Enums\GroupBuyStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\GroupBuy;
use Illuminate\Contracts\View\View;
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
    use CurrentStore;

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

    public function render(): View
    {
        $store = $this->currentStore();

        return view('livewire.seller.group-buys', [
            'deals' => GroupBuy::where('store_id', $store->id)
                ->with(['variant', 'product'])
                ->withCount('teams')
                ->latest('id')
                ->get(),
        ])->title(__('Group buys'));
    }
}
