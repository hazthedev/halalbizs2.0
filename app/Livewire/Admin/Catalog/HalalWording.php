<?php

namespace App\Livewire\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Livewire\Concerns\WithTable;
use App\Models\Product;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * HB-LAW-18: products whose name or description says "halal" with no approved,
 * in-force certificate. Review list only; nothing is blocked.
 */
#[Layout('layouts.admin')]
class HalalWording extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['updated' => 'updated_at'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['updated_at' => 'desc'];
    }

    public function render()
    {
        $term = trim($this->search);

        $products = $this->sortTable(Product::query()
            ->whereIn('status', [ProductStatus::Live, ProductStatus::PendingReview])
            ->halalWordingUnverified()
            ->with(['store', 'media'])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw(JsonSearch::clause('name'), [JsonSearch::pattern($term)])
                ->orWhereHas('store', fn ($s) => $s->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')))))
            ->paginate($this->tablePageSize());

        return view('livewire.admin.catalog.halal-wording', ['products' => $products])->title(__('Halal wording'));
    }
}
