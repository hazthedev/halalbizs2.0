<?php

namespace App\Livewire\Storefront\Subscribe;

use App\Enums\SubscriptionInterval;
use App\Models\Product;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * PDP subscribe-and-save panel (M2.8): pick a cadence and start a recurring,
 * discounted COD delivery of the product's default variant. Thin — all logic
 * lives in SubscriptionService.
 */
class Panel extends Component
{
    #[Locked]
    public int $productId;

    public int $interval = 30; // SubscriptionInterval value (days)

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
    }

    public function subscribe(SubscriptionService $subscriptions): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $product = Product::with('variants')->find($this->productId);
        $interval = SubscriptionInterval::tryFrom($this->interval) ?? SubscriptionInterval::Monthly;
        $variant = $product?->defaultVariant ?? $product?->variants->first();
        $address = auth()->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->first();

        if ($product === null || ! $product->isLive() || ! $product->cod_enabled || $variant === null) {
            $this->dispatch('toast', message: __('This item can’t be subscribed to right now.'), type: 'error');

            return;
        }

        if ($address === null) {
            $this->dispatch('toast', message: __('Add a delivery address first.'), type: 'error');
            $this->redirectRoute('account.addresses', navigate: true);

            return;
        }

        $subscriptions->subscribe(auth()->user(), $variant, $address, $interval);

        $this->dispatch('toast', message: __('Subscribed! Manage it any time under My subscriptions.'), type: 'success');
    }

    public function render(SubscriptionService $subscriptions): View
    {
        $product = Product::with('variants')->find($this->productId);
        $variant = $product?->defaultVariant ?? $product?->variants->first();

        $discountBp = (int) config('subscriptions.discount_bp', 500);
        $subPriceSen = $variant ? $subscriptions->discountedUnitSen($variant, $discountBp) : 0;

        return view('livewire.storefront.subscribe.panel', [
            'show' => $subscriptions->enabled() && $product !== null && $product->cod_enabled && $variant !== null,
            'discountBp' => $discountBp,
            'subPriceSen' => $subPriceSen,
            'intervals' => SubscriptionInterval::cases(),
        ]);
    }
}
