<?php

namespace App\Livewire\Storefront;

use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CartPage extends Component
{
    /**
     * Recently removed lines, kept so Undo can restore the original
     * quantity + selection: [variant_id => ['qty' => int, 'selected' => bool]].
     *
     * @var array<int, array{qty: int, selected: bool}>
     */
    public array $removed = [];

    public function updateQty(int $variantId, int $qty): void
    {
        $variant = ProductVariant::find($variantId);

        if ($variant !== null) {
            app(CartService::class)->updateQty(auth()->user(), $variant, $qty);
        }

        $this->reconcile();
    }

    public function removeLine(int $variantId): void
    {
        $variant = ProductVariant::find($variantId);

        if ($variant !== null) {
            $this->rememberRemoved($variantId);
            app(CartService::class)->removeItem(auth()->user(), $variant);
        }

        $this->reconcile();
        $this->dispatch(
            'toast',
            message: __('Removed from cart'),
            actionLabel: __('Undo'),
            actionEvent: 'undo-remove',
            actionPayload: ['variantId' => $variantId],
        );
    }

    #[On('undo-remove')]
    public function undoRemove(int $variantId): void
    {
        $service = app(CartService::class);
        $variant = ProductVariant::find($variantId);

        // hasItem() keeps the restore idempotent — MiniCart listens for the
        // same event, so only the first handler should re-add the line.
        if ($variant !== null && ! $service->hasItem(auth()->user(), $variantId)) {
            $removed = $this->removed[$variantId] ?? null;

            $service->addItem(auth()->user(), $variant, $removed['qty'] ?? 1);

            if (auth()->check() && ($removed['selected'] ?? true) === false) {
                $this->cartItems()->where('product_variant_id', $variantId)->update(['selected' => false]);
            }
        }

        unset($this->removed[$variantId]);
        $this->reconcile();
    }

    public function toggleSelected(int $variantId): void
    {
        if (auth()->guest()) {
            return;
        }

        $item = $this->cartItems()->where('product_variant_id', $variantId)->first();
        $item?->update(['selected' => ! $item->selected]);

        $this->reconcile();
    }

    public function toggleStoreSelected(int $storeId): void
    {
        if (auth()->guest()) {
            return;
        }

        $this->setSelected(
            $this->cartItems()
                ->whereHas('variant.product', fn ($query) => $query->where('store_id', $storeId))
                ->get()
        );

        $this->reconcile();
    }

    public function toggleAllSelected(): void
    {
        if (auth()->guest()) {
            return;
        }

        $this->setSelected($this->cartItems()->get());

        $this->reconcile();
    }

    #[On('cart-updated')]
    public function render(): View
    {
        $service = app(CartService::class);

        $groups = $service->groupedByStore(auth()->user())
            ->map(function ($lines) use ($service) {
                $presented = $lines->map(fn ($line) => $this->presentLine($line, $service))->values();
                $selectable = $presented->reject(fn ($line) => $line->excluded);

                return (object) [
                    'store' => $lines->first()->variant->product->store,
                    'lines' => $presented,
                    'allSelected' => $selectable->isNotEmpty() && $selectable->every(fn ($line) => $line->selected),
                    'subtotalSen' => (int) $selectable->filter(fn ($line) => $line->selected)->sum(fn ($line) => $line->lineTotalSen),
                ];
            })
            ->values();

        $selectable = $groups->flatMap(fn ($group) => $group->lines)->reject(fn ($line) => $line->excluded);
        $selected = $selectable->filter(fn ($line) => $line->selected);

        return view('livewire.storefront.cart-page', [
            'groups' => $groups,
            'allSelected' => $selectable->isNotEmpty() && $selectable->every(fn ($line) => $line->selected),
            'selectedCount' => $selected->count(),
            'itemsTotalSen' => (int) $selected->sum(fn ($line) => $line->lineTotalSen),
        ])->title(__('Cart'));
    }

    /**
     * Revalidate a cart line against live data: clamp over-stock quantities
     * (persisted) and flag lines that can no longer be bought.
     */
    private function presentLine(object $line, CartService $service): object
    {
        $variant = $line->variant;
        $unavailable = ! $variant->product->isLive();
        $outOfStock = ! $unavailable && $variant->stock < 1;
        $adjusted = false;
        $qty = $line->qty;

        if (! $unavailable && ! $outOfStock && $qty > $variant->stock) {
            $service->updateQty(auth()->user(), $variant, $variant->stock);
            $qty = $variant->stock;
            $adjusted = true;
        }

        return (object) [
            'variant' => $variant,
            'qty' => $qty,
            'selected' => auth()->guest() || (bool) $line->selected,
            'unavailable' => $unavailable,
            'outOfStock' => $outOfStock,
            'adjusted' => $adjusted,
            'excluded' => $unavailable || $outOfStock,
            'lineTotalSen' => $variant->effectivePriceSen() * $qty,
        ];
    }

    private function rememberRemoved(int $variantId): void
    {
        if (auth()->guest()) {
            $this->removed[$variantId] = [
                'qty' => app(CartService::class)->sessionItems()[$variantId] ?? 1,
                'selected' => true,
            ];

            return;
        }

        $item = $this->cartItems()->where('product_variant_id', $variantId)->first();

        $this->removed[$variantId] = [
            'qty' => $item?->qty ?? 1,
            'selected' => $item?->selected ?? true,
        ];
    }

    /**
     * Shopee-style toggle: if every given item is selected, deselect them
     * all; otherwise select them all.
     */
    private function setSelected(EloquentCollection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $target = ! $items->every(fn (CartItem $item) => $item->selected);

        CartItem::whereIn('id', $items->modelKeys())->update(['selected' => $target]);
    }

    private function cartItems(): HasMany
    {
        return app(CartService::class)->cartFor(auth()->user())->items();
    }

    private function reconcile(): void
    {
        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));
    }
}
