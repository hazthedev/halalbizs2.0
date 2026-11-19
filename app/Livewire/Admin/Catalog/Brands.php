<?php

namespace App\Livewire\Admin\Catalog;

use App\Livewire\Concerns\WithTable;
use App\Models\Brand;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Brand CRUD (docs/08 §D) — name + active toggle. Deleting a brand nulls
 * brand_id on its products (FK nullOnDelete); the confirm warns about it.
 */
#[Layout('layouts.admin')]
class Brands extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['name' => 'name', 'slug' => 'slug', 'active' => 'is_active', 'products' => 'products_count'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['name' => 'asc'];
    }

    public string $newName = '';

    public ?int $editingId = null;

    public string $editName = '';

    public function create(): void
    {
        $this->validate(
            ['newName' => ['required', 'string', 'max:255']],
            attributes: ['newName' => __('brand name')],
        );

        Brand::create(['name' => trim($this->newName), 'is_active' => true]);

        $this->newName = '';
        $this->dispatch('toast', message: __('Brand created'));
    }

    public function edit(int $brandId): void
    {
        $brand = Brand::query()->findOrFail($brandId);

        $this->editingId = $brand->id;
        $this->editName = $brand->name;
        $this->resetErrorBag();
    }

    public function update(): void
    {
        $this->validate(
            ['editName' => ['required', 'string', 'max:255']],
            attributes: ['editName' => __('brand name')],
        );

        Brand::query()->findOrFail($this->editingId)->update(['name' => trim($this->editName)]);

        $this->cancelEdit();
        $this->dispatch('toast', message: __('Brand updated'));
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editName']);
        $this->resetErrorBag();
    }

    public function toggleActive(int $brandId): void
    {
        $brand = Brand::query()->findOrFail($brandId);
        $brand->update(['is_active' => ! $brand->is_active]);
    }

    /** Products keep selling — they just lose the brand link (FK nulls it). */
    public function delete(int $brandId): void
    {
        $brand = Brand::query()->findOrFail($brandId);

        // Explicit, not just FK behaviour — keeps SQLite/MySQL identical.
        $brand->products()->update(['brand_id' => null]);
        $brand->delete();
        $this->resetPage();

        if ($this->editingId === $brandId) {
            $this->cancelEdit();
        }

        $this->dispatch('toast', message: __('Brand deleted'));
    }

    public function render()
    {
        return view('livewire.admin.catalog.brands', [
            'brands' => $this->sortTable(Brand::query()->withCount('products')
                ->when(trim($this->search) !== '', fn ($q) => $q->where(function ($q) {
                    $term = JsonSearch::pattern($this->search);
                    $q->whereRaw(JsonSearch::clause('name'), [$term])->orWhereRaw(JsonSearch::clause('slug'), [$term]);
                })))->paginate($this->tablePageSize()),
        ])->title(__('Brands'));
    }
}
