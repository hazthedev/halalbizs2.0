<?php

namespace App\Livewire\Admin\Content;

use App\Models\HomeSection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Home sections manager (docs/08 §G) — drives storefront Home (B1).
 * Payload keys MUST stay 'source' / 'limit' — Storefront\Home reads them.
 */
#[Layout('layouts.admin')]
class HomeSections extends Component
{
    /** One section per type; only missing types are addable. */
    public const TYPES = ['banner', 'category_grid', 'product_carousel', 'product_grid', 'recently_viewed'];

    public const SOURCES = ['latest', 'top'];

    public string $addType = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var array{en: string, ms: string} */
    public array $title = ['en' => '', 'ms' => ''];

    public string $source = 'latest';

    public string $limit = '12';

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'banner' => __('Banner carousel'),
            'category_grid' => __('Category grid'),
            'product_carousel' => __('Product carousel'),
            'product_grid' => __('Product grid'),
            'recently_viewed' => __('Recently viewed'),
            default => $type,
        };
    }

    public function addSection(): void
    {
        $missing = $this->missingTypes();

        if (! in_array($this->addType, $missing, true)) {
            return;
        }

        HomeSection::create([
            'type' => $this->addType,
            'title' => null,
            'payload' => match ($this->addType) {
                'category_grid' => ['limit' => 8],
                'product_carousel' => ['source' => 'latest', 'limit' => 12],
                'product_grid' => ['source' => 'top', 'limit' => 12],
                default => null,
            },
            'position' => ((int) HomeSection::max('position')) + 1,
            'is_active' => true,
        ]);

        $this->addType = '';
        $this->dispatch('toast', message: __('Section added'));
    }

    public function edit(int $sectionId): void
    {
        $section = HomeSection::findOrFail($sectionId);

        $this->resetErrorBag();
        $this->editingId = $section->id;
        $this->title = [
            'en' => $section->getTranslation('title', 'en', false) ?? '',
            'ms' => $section->getTranslation('title', 'ms', false) ?? '',
        ];
        $this->source = (string) ($section->payload['source'] ?? ($section->type === 'product_grid' ? 'top' : 'latest'));
        $this->limit = (string) ($section->payload['limit'] ?? ($section->type === 'category_grid' ? 8 : 12));
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'source', 'limit']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $section = HomeSection::findOrFail($this->editingId);

        $rules = [
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
        ];

        if (in_array($section->type, ['category_grid', 'product_carousel', 'product_grid'], true)) {
            $rules['limit'] = ['required', 'integer', 'min:1', 'max:48'];
        }

        if (in_array($section->type, ['product_carousel', 'product_grid'], true)) {
            $rules['source'] = ['required', 'in:'.implode(',', self::SOURCES)];
        }

        $this->validate($rules, attributes: ['title.en' => __('title (English)')]);

        // en is ALWAYS written (fallback locale); ms only when filled.
        $section->setTranslation('title', 'en', trim($this->title['en']));

        if (trim($this->title['ms'] ?? '') !== '') {
            $section->setTranslation('title', 'ms', trim($this->title['ms']));
        } else {
            $section->forgetTranslation('title', 'ms');
        }

        // Payload contract per type — keys identical to Storefront\Home::sectionData().
        $section->payload = match ($section->type) {
            'category_grid' => ['limit' => (int) $this->limit],
            'product_carousel', 'product_grid' => ['source' => $this->source, 'limit' => (int) $this->limit],
            default => null,
        };

        $section->save();

        $this->dispatch('toast', message: __('Section saved'));
        $this->cancel();
    }

    public function toggleActive(int $sectionId): void
    {
        $section = HomeSection::findOrFail($sectionId);
        $section->update(['is_active' => ! $section->is_active]);

        $this->dispatch('toast', message: $section->is_active ? __('Section enabled') : __('Section disabled'));
    }

    /** Swap with the neighbour, then re-index positions 0..n. */
    public function move(int $sectionId, int $direction): void
    {
        $sections = HomeSection::orderBy('position')->orderBy('id')->get()->values();
        $index = $sections->search(fn (HomeSection $section) => $section->id === $sectionId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($sections[$target])) {
            return;
        }

        $ordered = $sections->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $section) {
            if ($section->position !== $position) {
                $section->update(['position' => $position]);
            }
        }
    }

    public function delete(int $sectionId): void
    {
        HomeSection::findOrFail($sectionId)->delete();

        if ($this->editingId === $sectionId) {
            $this->cancel();
        }

        $this->dispatch('toast', message: __('Section removed'));
    }

    public function render()
    {
        return view('livewire.admin.content.home-sections', [
            'sections' => HomeSection::orderBy('position')->orderBy('id')->get(),
            'missingTypes' => $this->missingTypes(),
        ])->title(__('Home sections'));
    }

    /** @return array<int, string> */
    private function missingTypes(): array
    {
        $existing = HomeSection::pluck('type')->all();

        return array_values(array_diff(self::TYPES, $existing));
    }
}
