<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\ProductVariant;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class StorePage extends Component
{
    use InteractsWithCart;

    public Store $store;

    public int $perPage = 24;

    public string $sort = 'latest';

    private const SORTS = ['latest', 'top', 'price_asc', 'price_desc'];

    public function mount(Store $store): void
    {
        abort_unless($store->isApproved(), 404);

        $this->store = $store;
    }

    public function loadMore(): void
    {
        $this->perPage += 24;
    }

    public function updatedSort(string $value): void
    {
        if (! in_array($value, self::SORTS, true)) {
            $this->sort = 'latest';
        }

        $this->perPage = 24;
    }

    public function render()
    {
        $base = $this->store->products()->live();

        $total = (clone $base)->count();

        $minPriceSub = ProductVariant::query()
            ->selectRaw('min(price_sen)')
            ->whereColumn('product_variants.product_id', 'products.id');

        $products = $base
            ->with(['variants', 'media', 'store'])
            ->when($this->sort === 'latest', fn ($query) => $query->orderByDesc('published_at'))
            ->when($this->sort === 'top', fn ($query) => $query->orderByDesc('sold_count'))
            ->when($this->sort === 'price_asc', fn ($query) => $query->orderBy($minPriceSub))
            ->when($this->sort === 'price_desc', fn ($query) => $query->orderByDesc($minPriceSub))
            ->orderByDesc('products.id')
            ->take($this->perPage)
            ->get();

        return view('livewire.storefront.store-page', [
            'products' => $products,
            'total' => $total,
            'wishlistedIds' => $this->wishlistedIds(),
        ])->title($this->store->name);
    }
}
