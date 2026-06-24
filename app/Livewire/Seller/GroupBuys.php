<?php

namespace App\Livewire\Seller;

use App\Enums\GroupBuyStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\GroupBuy;
use App\Models\Product;
use App\Support\RinggitInput;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    public bool $showForm = false;

    public ?int $productId = null;

    public ?int $variantId = null;

    public string $groupPrice = '';

    public int $targetSize = 2;

    public int $windowHours = 24;

    public string $endsAt = '';

    public function mount(): void
    {
        abort_unless(config('groupbuy.enabled', true), 404);

        $this->targetSize = (int) config('groupbuy.default_target_size', 2);
        $this->windowHours = (int) config('groupbuy.default_window_hours', 24);
        $this->endsAt = now()->addWeek()->format('Y-m-d\TH:i');
    }

    public function create(): void
    {
        $this->reset(['productId', 'variantId', 'groupPrice']);
        $this->targetSize = (int) config('groupbuy.default_target_size', 2);
        $this->windowHours = (int) config('groupbuy.default_window_hours', 24);
        $this->endsAt = now()->addWeek()->format('Y-m-d\TH:i');
        $this->showForm = true;
    }

    public function save(): void
    {
        $store = $this->currentStore();

        $validated = $this->validate([
            'productId' => 'required|integer',
            'variantId' => 'required|integer',
            'groupPrice' => 'required|string',
            'targetSize' => 'required|integer|min:2|max:100',
            'windowHours' => 'required|integer|min:1|max:168',
            'endsAt' => 'required|date|after:now',
        ]);

        $product = Product::where('id', $validated['productId'])->where('store_id', $store->id)->first();
        $variant = $product?->variants->firstWhere('id', $validated['variantId']);

        if ($product === null || $variant === null) {
            throw ValidationException::withMessages(['productId' => __('Choose one of your products and a variant.')]);
        }

        $priceSen = RinggitInput::toSen($validated['groupPrice']);

        if ($priceSen === null || $priceSen <= 0 || $priceSen >= $variant->effectivePriceSen()) {
            throw ValidationException::withMessages(['groupPrice' => __('The group price must be a positive amount below the current price.')]);
        }

        GroupBuy::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'group_price_sen' => $priceSen,
            'target_size' => $validated['targetSize'],
            'team_window_hours' => $validated['windowHours'],
            'status' => GroupBuyStatus::Active,
            'starts_at' => now(),
            'ends_at' => Carbon::parse($validated['endsAt']),
        ]);

        $this->showForm = false;
        $this->dispatch('toast', message: __('Group-buy deal created.'), type: 'success');
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
            'products' => Product::where('store_id', $store->id)->with('variants')->orderBy('id')->get(),
        ])->title(__('Group buys'));
    }
}
