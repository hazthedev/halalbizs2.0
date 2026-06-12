<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Attribute CRUD + per-attribute value management (docs/08 §D).
 * Inline forms; EN required, BM optional on every translatable write.
 */
#[Layout('layouts.admin')]
class Attributes extends Component
{
    // ── Create form ────────────────────────────────────────────────────
    /** @var array{en: string, ms: string} */
    public array $name = ['en' => '', 'ms' => ''];

    public bool $isFilterable = true;

    // ── Inline edit ────────────────────────────────────────────────────
    public ?int $editingId = null;

    /** @var array{en: string, ms: string} */
    public array $editName = ['en' => '', 'ms' => ''];

    // ── Values panel ───────────────────────────────────────────────────
    public ?int $managingId = null;

    /** @var array{en: string, ms: string} */
    public array $valueDraft = ['en' => '', 'ms' => ''];

    public function create(): void
    {
        $this->validate([
            'name.en' => ['required', 'string', 'max:255'],
            'name.ms' => ['nullable', 'string', 'max:255'],
        ], attributes: ['name.en' => __('attribute name (English)')]);

        Attribute::create([
            'name' => $this->translationPayload($this->name),
            'is_filterable' => $this->isFilterable,
        ]);

        $this->name = ['en' => '', 'ms' => ''];
        $this->isFilterable = true;

        $this->dispatch('toast', message: __('Attribute created'));
    }

    public function edit(int $attributeId): void
    {
        $attribute = Attribute::query()->findOrFail($attributeId);

        $this->editingId = $attribute->id;
        $this->editName = [
            'en' => $attribute->getTranslation('name', 'en'),
            'ms' => $attribute->getTranslation('name', 'ms', false) ?? '',
        ];
        $this->resetErrorBag();
    }

    public function update(): void
    {
        $this->validate([
            'editName.en' => ['required', 'string', 'max:255'],
            'editName.ms' => ['nullable', 'string', 'max:255'],
        ], attributes: ['editName.en' => __('attribute name (English)')]);

        $attribute = Attribute::query()->findOrFail($this->editingId);

        $attribute->setTranslation('name', 'en', trim($this->editName['en']));

        if (trim($this->editName['ms'] ?? '') !== '') {
            $attribute->setTranslation('name', 'ms', trim($this->editName['ms']));
        } else {
            $attribute->forgetTranslation('name', 'ms');
        }

        $attribute->save();

        $this->cancelEdit();
        $this->dispatch('toast', message: __('Attribute updated'));
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId']);
        $this->editName = ['en' => '', 'ms' => ''];
        $this->resetErrorBag();
    }

    public function toggleFilterable(int $attributeId): void
    {
        $attribute = Attribute::query()->findOrFail($attributeId);
        $attribute->update(['is_filterable' => ! $attribute->is_filterable]);
    }

    public function deleteAttribute(int $attributeId): void
    {
        Attribute::query()->findOrFail($attributeId)->delete();

        if ($this->managingId === $attributeId) {
            $this->reset(['managingId']);
        }

        $this->dispatch('toast', message: __('Attribute deleted'));
    }

    // ── Values ─────────────────────────────────────────────────────────

    public function manageValues(int $attributeId): void
    {
        $this->managingId = $this->managingId === $attributeId ? null : $attributeId;
        $this->valueDraft = ['en' => '', 'ms' => ''];
        $this->resetErrorBag();
    }

    public function addValue(): void
    {
        $this->validate([
            'valueDraft.en' => ['required', 'string', 'max:255'],
            'valueDraft.ms' => ['nullable', 'string', 'max:255'],
        ], attributes: ['valueDraft.en' => __('value (English)')]);

        $attribute = Attribute::query()->findOrFail($this->managingId);

        $attribute->values()->create([
            'value' => $this->translationPayload($this->valueDraft),
            'position' => (int) ($attribute->values()->max('position') ?? -1) + 1,
        ]);

        $this->valueDraft = ['en' => '', 'ms' => ''];
    }

    public function removeValue(int $valueId): void
    {
        AttributeValue::query()->findOrFail($valueId)->delete();
    }

    /** Swap position with the previous (-1) or next (+1) value. */
    public function moveValue(int $valueId, int $direction): void
    {
        $value = AttributeValue::query()->findOrFail($valueId);

        $values = AttributeValue::query()
            ->where('attribute_id', $value->attribute_id)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $values->search(fn (AttributeValue $candidate) => $candidate->id === $value->id);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || $target >= $values->count()) {
            return;
        }

        $ordered = $values->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $row) {
            if ($row->position !== $position) {
                $row->update(['position' => $position]);
            }
        }
    }

    public function render()
    {
        return view('livewire.admin.catalog.attributes', [
            'attributeList' => Attribute::query()->withCount('values')->orderBy('slug')->get(),
            'managedValues' => $this->managingId !== null
                ? AttributeValue::query()->where('attribute_id', $this->managingId)->orderBy('position')->orderBy('id')->get()
                : collect(),
        ])->title(__('Attributes'));
    }

    /**
     * @param  array{en: string, ms: string}  $input
     * @return array<string, string> en always present; ms only when filled
     */
    private function translationPayload(array $input): array
    {
        $payload = ['en' => trim($input['en'])];

        if (trim($input['ms'] ?? '') !== '') {
            $payload['ms'] = trim($input['ms']);
        }

        return $payload;
    }
}
