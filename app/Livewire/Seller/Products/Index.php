<?php

namespace App\Livewire\Seller\Products;

use App\Enums\ProductStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Category;
use App\Models\Product;
use App\Settings\ModerationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seller products index (docs/07 §A3) — store-scoped datagrid with
 * status/category/low-stock filters, name/SKU search, row + bulk actions.
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore, WithPagination;

    public const PER_PAGE = 20;

    public const LOW_STOCK_THRESHOLD = 5;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: null)]
    public ?int $category = null;

    #[Url(as: 'low_stock', except: false)]
    public bool $lowStock = false;

    #[Url(except: '')]
    public string $search = '';

    /** @var array<int, string> selected product ids (checkbox column) */
    public array $selected = [];

    public bool $selectPage = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'category', 'lowStock', 'search'], true)) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function updatedPaginators(): void
    {
        $this->clearSelection();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->results()->pluck('id')->map(fn (int $id) => (string) $id)->all()
            : [];
    }

    public function duplicate(int $productId): void
    {
        $product = $this->ownProduct($productId)->load(['options.values', 'variants']);

        DB::transaction(function () use ($product) {
            $copy = $product->replicate(['slug', 'sold_count', 'rating_avg', 'rating_count', 'published_at', 'deleted_at']);
            $copy->status = ProductStatus::Draft;
            $copy->sold_count = 0;
            $copy->rating_avg = 0;
            $copy->rating_count = 0;
            $copy->published_at = null;
            $copy->slug = null;

            $copy->setTranslation('name', 'en', $product->getTranslation('name', 'en').' (copy)');

            $nameMs = $product->getTranslation('name', 'ms', false);

            if ($nameMs !== null && $nameMs !== '') {
                $copy->setTranslation('name', 'ms', $nameMs.' (copy)');
            }

            $copy->save();

            // Rebuild the option tree, remembering old → new value ids.
            $valueIdMap = [];

            foreach ($product->options as $option) {
                $newOption = $copy->options()->create(['name' => $option->name, 'position' => $option->position]);

                foreach ($option->values as $value) {
                    $newValue = $newOption->values()->create(['value' => $value->value, 'position' => $value->position]);
                    $valueIdMap[$value->id] = $newValue->id;
                }
            }

            foreach ($product->variants as $variant) {
                $copy->variants()->create([
                    'sku' => $variant->sku,
                    'options_label' => $variant->options_label,
                    'option_value_ids' => $variant->option_value_ids === null
                        ? null
                        : array_values(array_map(fn (int $id) => $valueIdMap[$id], $variant->option_value_ids)),
                    'price_sen' => $variant->price_sen,
                    'sale_price_sen' => $variant->sale_price_sen,
                    'sale_starts_at' => $variant->sale_starts_at,
                    'sale_ends_at' => $variant->sale_ends_at,
                    'stock' => $variant->stock,
                    'is_default' => $variant->is_default,
                    'position' => $variant->position,
                ]);
            }
        });

        $this->dispatch('toast', message: __('Duplicated as a draft. Images are not copied — add them to the new product.'));
    }

    public function delist(int $productId): void
    {
        $product = $this->ownProduct($productId);

        if ($product->status !== ProductStatus::Live) {
            return;
        }

        $product->update(['status' => ProductStatus::Delisted]);

        $this->dispatch('toast', message: __('Product delisted'));
    }

    public function relist(int $productId): void
    {
        $product = $this->ownProduct($productId);

        if ($product->status !== ProductStatus::Delisted) {
            return;
        }

        $needsApproval = app(ModerationSettings::class)->require_product_approval;

        $product->update(['status' => $needsApproval ? ProductStatus::PendingReview : ProductStatus::Live]);

        $this->dispatch('toast', message: $needsApproval
            ? __('Submitted for review — it goes live once approved.')
            : __('Product relisted'));
    }

    public function bulkDelist(): void
    {
        $products = $this->selectedProducts()->where('status', ProductStatus::Live)->get();

        foreach ($products as $product) {
            $product->update(['status' => ProductStatus::Delisted]);
        }

        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{1}:count product delisted|[2,*]:count products delisted', $products->count(), ['count' => $products->count()]));
    }

    /** Drafts only — anything else stays untouched. */
    public function bulkDelete(): void
    {
        $products = $this->selectedProducts()->where('status', ProductStatus::Draft)->get();

        foreach ($products as $product) {
            $product->delete();
        }

        $this->clearSelection();
        $this->resetPage();

        $this->dispatch('toast', message: trans_choice('{1}:count draft deleted|[2,*]:count drafts deleted', $products->count(), ['count' => $products->count()]));
    }

    public function render()
    {
        return view('livewire.seller.products.index', [
            'products' => $this->results(),
            'statuses' => ProductStatus::cases(),
            'categoryOptions' => $this->categoryOptions(),
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
        ])->title(__('Products'));
    }

    private function results(): LengthAwarePaginator
    {
        return $this->query()->paginate(self::PER_PAGE);
    }

    private function query(): Builder
    {
        $query = Product::query()
            ->where('store_id', $this->currentStore()->id)
            ->with(['media', 'variants'])
            ->withCount('variants');

        if ($this->status !== '' && ProductStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        if ($this->category !== null) {
            $category = Category::find($this->category);

            if ($category !== null) {
                $query->whereIn('category_id', $category->descendantIds());
            }
        }

        if ($this->lowStock) {
            $query->whereHas('variants', fn (Builder $variants) => $variants->where('stock', '<', self::LOW_STOCK_THRESHOLD));
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', "%{$term}%"));
            });
        }

        return $query->latest('updated_at')->latest('id');
    }

    private function ownProduct(int $productId): Product
    {
        return Product::query()
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($productId);
    }

    private function selectedProducts(): Builder
    {
        return Product::query()
            ->where('store_id', $this->currentStore()->id)
            ->whereIn('id', array_map(intval(...), $this->selected));
    }

    private function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }

    /** @return array<int, string> flattened 3-level tree with depth markers */
    private function categoryOptions(): array
    {
        $byParent = Category::query()
            ->active()
            ->orderBy('position')
            ->get()
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, &$options) {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $options[$category->id] = str_repeat('— ', $depth).$category->getTranslation('name', app()->getLocale());
                $walk($category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $options;
    }
}
