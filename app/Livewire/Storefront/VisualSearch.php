<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Product;
use App\Services\VectorSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Visual search (M2.3): upload a photo, embed it (colour histogram locally; a
 * vision model in prod) and rank live products by visual similarity. Reuses the
 * cart/wishlist card behaviour; no checkout impact.
 */
#[Layout('layouts.storefront')]
class VisualSearch extends Component
{
    use InteractsWithCart;
    use WithFileUploads;

    public $image = null;

    /** @var array<int, int> */
    public array $resultIds = [];

    public function mount(): void
    {
        abort_unless(config('search.enabled', true), 404);
    }

    public function updatedImage(): void
    {
        $this->validate(['image' => 'image|max:8192']);

        $this->resultIds = app(VectorSearchService::class)->visualSearch($this->image->getRealPath(), 24);
    }

    public function clear(): void
    {
        $this->reset('image', 'resultIds');
    }

    public function render(): View
    {
        $products = $this->resultIds === []
            ? collect()
            : Product::query()->live()->with(['media', 'variants', 'store'])
                ->whereIn('id', $this->resultIds)
                ->get()
                ->sortBy(fn (Product $p) => array_search($p->id, $this->resultIds, true))
                ->values();

        return view('livewire.storefront.visual-search', [
            'products' => $products,
            'wishlistedIds' => $this->wishlistedIds(),
        ])->title(__('Search by image'));
    }
}
