<?php

namespace App\Livewire\Seller\GroupBuys;

use App\Enums\GroupBuyStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\GroupBuy;
use App\Models\Product;
use App\Support\DisplayTime;
use App\Support\RinggitInput;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller group-buy deals (M2.6). Create a per-variant group price that unlocks
 * when a target number of shoppers team up within the window. Store-scoped via
 * CurrentStore; price stays integer sen (Hard Rule 1).
 */
#[Layout('layouts.seller')]
class Form extends Component
{
    use CurrentStore;

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
        $this->endsAt = DisplayTime::now()->addWeek()->format('Y-m-d\TH:i');
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
            'endsAt' => 'required|date|after:'.DisplayTime::now()->toDateTimeString(),
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
            'ends_at' => DisplayTime::parse($validated['endsAt']),
        ]);

        session()->flash('status', __('Group-buy deal created.'));
        $this->redirectRoute('seller.group-buys.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.seller.group-buys.form', [
            'products' => Product::where('store_id', $this->currentStore()->id)->with('variants')->orderBy('id')->get(),
        ])->title(__('Create deal'));
    }
}
