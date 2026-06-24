<?php

namespace App\Livewire\Storefront;

use App\Models\FlashSale as FlashSaleModel;
use App\Models\FlashSaleItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Flash-sale hub (docs/ROADMAP.md M1.2): live deal lines with promo prices, a
 * claimed progress bar, and a countdown to the soonest end time. Polls so the
 * "% claimed" bar stays fresh during a drop.
 */
#[Layout('layouts.storefront')]
class FlashSale extends Component
{
    public function render(): View
    {
        $items = FlashSaleItem::query()
            ->whereHas('flashSale', fn ($sale) => $sale->live())
            ->with(['variant.product.media', 'variant.product.store', 'flashSale'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FlashSaleItem $item) => $item->variant?->product?->isLive())
            ->values();

        $endsAt = FlashSaleModel::query()->live()->min('ends_at');

        return view('livewire.storefront.flash-sale', [
            'items' => $items,
            'endsAt' => $endsAt,
        ])->title(__('Flash Sale'));
    }
}
