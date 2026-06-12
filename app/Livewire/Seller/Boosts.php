<?php

namespace App\Livewire\Seller;

use App\Enums\BoostStatus;
use App\Exceptions\CheckoutException;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Services\LedgerService;
use App\Settings\BoostSettings;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Paid product boosts (Phase-4). Cost = days × price_sen_per_day, integer
 * sen only. The fee is debited from the store's available ledger balance by
 * LedgerService::chargeBoost (the ONLY ledger write path) inside the same
 * transaction that creates the boost row. Cancelling stops the placement
 * immediately — no refund for unused days in v1.
 */
#[Layout('layouts.seller')]
class Boosts extends Component
{
    use CurrentStore;

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 30;

    /** Preselected by the Products index "Boost" row action (?product={id}). */
    #[Url(as: 'product', except: null)]
    public ?int $productId = null;

    public int $days = 7;

    public function mount(): void
    {
        // Never trust the query string — only the store's own live products.
        if ($this->productId !== null && $this->ownLiveProduct($this->productId) === null) {
            $this->productId = null;
        }
    }

    public function boost(LedgerService $ledger): void
    {
        $settings = app(BoostSettings::class);

        if (! $settings->enabled) {
            $this->dispatch('toast', message: __('Boosts are switched off right now — check back soon.'), type: 'error');

            return;
        }

        $this->validate([
            'productId' => ['required', 'integer'],
            'days' => ['required', 'integer', 'between:'.self::MIN_DAYS.','.self::MAX_DAYS],
        ], [
            'productId.required' => __('Choose one of your live products.'),
            'days.between' => __('Boosts run between :min and :max days.', ['min' => self::MIN_DAYS, 'max' => self::MAX_DAYS]),
        ]);

        $store = $this->currentStore();
        $product = $this->ownLiveProduct($this->productId);

        if ($product === null) {
            $this->addError('productId', __('Choose one of your live products.'));

            return;
        }

        $amountSen = $this->days * $settings->price_sen_per_day;

        try {
            DB::transaction(function () use ($ledger, $settings, $store, $product, $amountSen) {
                // chargeBoost locks the store row, so the max-active count
                // below is serialized against concurrent boost attempts too.
                $ledger->chargeBoost($store, $amountSen, $product);

                if ($store->boosts()->active()->count() >= $settings->max_active_per_store) {
                    throw new CheckoutException(__('You can run up to :max boosts at a time — wait for one to end or cancel it first.', [
                        'max' => $settings->max_active_per_store,
                    ]));
                }

                ProductBoost::create([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays($this->days),
                    'amount_sen' => $amountSen,
                    'status' => BoostStatus::Active,
                ]);
            });
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->reset('productId');
        $this->days = 7;

        $this->dispatch('toast', message: __('Boost started — :name now leads category, search and home placements.', [
            'name' => $product->getTranslation('name', app()->getLocale()),
        ]));
    }

    /** Stops the placement immediately. No refund for unused days (v1). */
    public function cancel(int $boostId): void
    {
        $boost = $this->currentStore()->boosts()->find($boostId);

        abort_if($boost === null, 404);

        if ($boost->status !== BoostStatus::Active) {
            return;
        }

        $boost->update(['status' => BoostStatus::Cancelled]);

        $this->dispatch('toast', message: __('Boost cancelled — the remaining days are not refunded.'));
    }

    public function render()
    {
        $store = $this->currentStore();
        $settings = app(BoostSettings::class);

        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $this->days));

        return view('livewire.seller.boosts', [
            'settings' => $settings,
            'products' => Product::query()->live()->where('store_id', $store->id)->orderBy('id')->get(),
            'boosts' => $store->boosts()->with('product')->latest()->latest('id')->take(50)->get(),
            'activeCount' => $store->boosts()->active()->count(),
            'costSen' => $days * $settings->price_sen_per_day,
            'availableSen' => app(LedgerService::class)->availableBalanceSen($store),
        ])->title(__('Boosts'));
    }

    private function ownLiveProduct(?int $productId): ?Product
    {
        if ($productId === null) {
            return null;
        }

        return Product::query()
            ->live()
            ->where('store_id', $this->currentStore()->id)
            ->find($productId);
    }
}
