<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Attribute;
use App\Models\Category;
use App\Support\ContentLocales;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Category tree governance (docs/08 §D) — 3-level tree with per-node
 * reorder/active toggle, EN/BM edit panel, per-category commission rate,
 * image, and attribute mapping (category_attribute pivot).
 */
#[Layout('layouts.admin')]
class Categories extends Component
{
    use WithFileUploads;

    public const MAX_DEPTH = 3;

    // ── Edit / create panel ────────────────────────────────────────────
    public bool $formOpen = false;

    public ?int $editingId = null;

    public ?int $parentId = null;

    /** @var array{en: string, ms: string, vi: string} */
    public array $name = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $description = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $commissionRate = '';

    /**
     * '' inherit · '1' require · '0' exempt (audit H-6).
     *
     * A string, not a bool, because the meaningful state here is THREE-valued:
     * null inherits from the nearest flagged ancestor, which is what lets the
     * rule be set once on Groceries & Pantry. A checkbox cannot say "inherit".
     */
    public string $requiresHalalCertificate = '';

    public bool $isActive = true;

    public ?TemporaryUploadedFile $image = null;

    /** @var array<int, string> attribute ids mapped to this category */
    public array $selectedAttributeIds = [];

    // ── Panel lifecycle ────────────────────────────────────────────────

    public function startCreate(?int $parentId = null): void
    {
        $this->resetForm();

        if ($parentId !== null) {
            $parent = Category::query()->findOrFail($parentId);

            if ($this->depthOf($parent) >= self::MAX_DEPTH) {
                $this->addError('parent', __('Categories go three levels deep at most — this one is already at the bottom level.'));

                return;
            }

            $this->parentId = $parent->id;
        }

        $this->formOpen = true;
    }

    public function edit(int $categoryId): void
    {
        $this->resetForm();

        $category = Category::query()->with('attributes')->findOrFail($categoryId);

        $this->editingId = $category->id;
        $this->parentId = $category->parent_id;
        $this->name = ContentLocales::read($category, 'name');
        $this->description = ContentLocales::read($category, 'description');
        $this->requiresHalalCertificate = $category->requires_halal_certificate === null
            ? ''
            : ($category->requires_halal_certificate ? '1' : '0');
        $this->commissionRate = $category->commission_rate === null
            ? ''
            : rtrim(rtrim(number_format((float) $category->commission_rate, 2, '.', ''), '0'), '.');
        $this->isActive = $category->is_active;
        $this->selectedAttributeIds = $category->attributes->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'name.en' => ['required', 'string', 'max:255'],
            'name.ms' => ['nullable', 'string', 'max:255'],
            'name.vi' => ['nullable', 'string', 'max:255'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'description.ms' => ['nullable', 'string', 'max:2000'],
            'description.vi' => ['nullable', 'string', 'max:2000'],
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requiresHalalCertificate' => ['in:,1,0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'selectedAttributeIds' => ['array'],
            'selectedAttributeIds.*' => [Rule::exists('attributes', 'id')],
        ], attributes: [
            'name.en' => __('category name (English)'),
            'commissionRate' => __('commission rate'),
        ]);

        // Depth is re-checked on save — the tree may have changed since the panel opened.
        $parent = $this->parentId !== null ? Category::query()->find($this->parentId) : null;

        if ($this->parentId !== null && $parent === null) {
            $this->addError('parent', __('The parent category no longer exists — refresh and try again.'));

            return;
        }

        if ($parent !== null && $this->depthOf($parent) >= self::MAX_DEPTH) {
            $this->addError('parent', __('Categories go three levels deep at most — this one is already at the bottom level.'));

            return;
        }

        $category = $this->editingId !== null
            ? Category::query()->findOrFail($this->editingId)
            : new Category([
                'parent_id' => $this->parentId,
                'position' => (int) (Category::query()->where('parent_id', $this->parentId)->max('position') ?? -1) + 1,
            ]);

        $category->commission_rate = trim($this->commissionRate) === '' ? null : $this->commissionRate;
        $category->requires_halal_certificate = $this->requiresHalalCertificate === '' ? null : (bool) (int) $this->requiresHalalCertificate;
        $category->is_active = $this->isActive;

        ContentLocales::write($category, 'name', $this->name);
        ContentLocales::write($category, 'description', $this->description, englishRequired: false);

        $category->save();

        if ($this->image !== null) {
            $category->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        $category->attributes()->sync(array_map(intval(...), $this->selectedAttributeIds));

        $this->dispatch('toast', message: $this->editingId !== null ? __('Category updated') : __('Category created'));
        $this->resetForm();
    }

    public function removeImage(): void
    {
        if ($this->editingId === null) {
            $this->image = null;

            return;
        }

        Category::query()->findOrFail($this->editingId)->clearMediaCollection('image');
        $this->image = null;
    }

    // ── Row actions ────────────────────────────────────────────────────

    public function toggleActive(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    /** Swap position with the previous (-1) or next (+1) sibling. */
    public function move(int $categoryId, int $direction): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $siblings = Category::query()
            ->where('parent_id', $category->parent_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (Category $sibling) => $sibling->id === $category->id);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || $target >= $siblings->count()) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $sibling) {
            if ($sibling->position !== $position) {
                $sibling->update(['position' => $position]);
            }
        }
    }

    /** Delete only when the node has no products and no children. */
    public function delete(int $categoryId): void
    {
        $category = Category::query()->withCount(['products', 'children'])->findOrFail($categoryId);

        if ($category->children_count > 0 || $category->products_count > 0) {
            $this->dispatch('toast', message: __('This category still has products or sub-categories — move them first.'), type: 'error');

            return;
        }

        $category->delete();

        $this->dispatch('toast', message: __('Category deleted'));
    }

    public function render()
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('livewire.admin.catalog.categories', [
            'byParent' => $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0),
            'allAttributes' => Attribute::query()->orderBy('slug')->get(),
            'parentName' => $this->parentId !== null
                ? Category::query()->find($this->parentId)?->getTranslation('name', 'en')
                : null,
            'editingImageUrl' => $this->editingId !== null
                ? (Category::query()->find($this->editingId)?->getFirstMediaUrl('image') ?: null)
                : null,
        ])->title(__('Categories'));
    }

    private function resetForm(): void
    {
        $this->reset(['formOpen', 'editingId', 'parentId', 'commissionRate', 'requiresHalalCertificate', 'isActive', 'image', 'selectedAttributeIds']);
        $this->name = ContentLocales::blank();
        $this->description = ContentLocales::blank();
        $this->resetErrorBag();
    }

    /** 1 = root, 2 = child, 3 = leaf. */
    private function depthOf(Category $category): int
    {
        $depth = 1;
        $node = $category;

        while ($node->parent_id !== null && ($node = $node->parent) !== null) {
            $depth++;
        }

        return $depth;
    }
}
