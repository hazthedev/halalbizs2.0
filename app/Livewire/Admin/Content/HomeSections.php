<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\HomeSection;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Home sections manager (docs/08 §G) — drives storefront Home (B1).
 * Payload keys MUST stay 'source' / 'limit' — App\Services\HomeLayout reads them.
 */
#[Layout('layouts.admin')]
class HomeSections extends Component
{
    use ManagesDrafts;

    /** One section per type; only missing types are addable. */
    public const TYPES = ['banner', 'category_grid', 'product_carousel', 'product_grid', 'recommended', 'recently_viewed'];

    public const SOURCES = ['latest', 'top'];

    public string $addType = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $source = 'latest';

    public string $limit = '12';

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'banner' => __('Banner carousel'),
            'category_grid' => __('Category grid'),
            'product_carousel' => __('Product carousel'),
            'product_grid' => __('Product grid'),
            'recommended' => __('Recommended for you'),
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

        $section = new HomeSection([
            'type' => $this->addType,
            'title' => null,
            'payload' => match ($this->addType) {
                'category_grid' => ['limit' => 8],
                'product_carousel' => ['source' => 'latest', 'limit' => 12],
                'product_grid' => ['source' => 'top', 'limit' => 12],
                default => null,
            },
            'position' => ((int) $this->previewed()->max('position')) + 1,
            'is_active' => true,
        ]);

        // Stored hidden; its draft switches it on when published.
        app(ContentDrafts::class)->stage($section, auth()->user());

        $this->addType = '';
        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function edit(int $sectionId): void
    {
        $section = app(ContentDrafts::class)->preview(HomeSection::findOrFail($sectionId));

        $this->resetErrorBag();
        $this->editingId = $section->id;
        $this->title = ContentLocales::read($section, 'title');
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
        $section = app(ContentDrafts::class)->preview(HomeSection::findOrFail($this->editingId));

        $rules = [
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
        ];

        if (in_array($section->type, ['category_grid', 'product_carousel', 'product_grid'], true)) {
            $rules['limit'] = ['required', 'integer', 'min:1', 'max:48'];
        }

        if (in_array($section->type, ['product_carousel', 'product_grid'], true)) {
            $rules['source'] = ['required', 'in:'.implode(',', self::SOURCES)];
        }

        $this->validate($rules, attributes: ['title.en' => __('title (English)')]);

        ContentLocales::write($section, 'title', $this->title, englishRequired: false);

        // Payload contract per type — keys identical to HomeLayout::sectionData().
        $section->payload = match ($section->type) {
            'category_grid' => ['limit' => (int) $this->limit],
            'product_carousel', 'product_grid' => ['source' => $this->source, 'limit' => (int) $this->limit],
            default => null,
        };

        app(ContentDrafts::class)->stage($section, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
        $this->cancel();
    }

    public function toggleActive(int $sectionId): void
    {
        $section = app(ContentDrafts::class)->preview(HomeSection::findOrFail($sectionId));
        $section->is_active = ! $section->is_active;
        app(ContentDrafts::class)->stage($section, auth()->user());

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    /** Swap with the neighbour, then re-index positions 0..n. */
    public function move(int $sectionId, int $direction): void
    {
        $sections = $this->previewed();
        $index = $sections->search(fn (HomeSection $section) => $section->id === $sectionId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($sections[$target])) {
            return;
        }

        $ordered = $sections->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $section) {
            if ((int) $section->position !== $position) {
                $section->position = $position;
                app(ContentDrafts::class)->stage($section, auth()->user());
            }
        }
    }

    /** A reorder touches several rows, so one button publishes every section draft. */
    public function publishAllDrafts(): void
    {
        $this->authorize('cms.publish');

        HomeSection::whereHas('draft')->get()->each(fn (HomeSection $section) => app(ContentDrafts::class)->publish($section, auth()->user()));

        $this->dispatch('toast', message: __('Published. It is live now.'));
    }

    public function delete(int $sectionId): void
    {
        $this->authorize('cms.publish');

        HomeSection::findOrFail($sectionId)->delete();

        if ($this->editingId === $sectionId) {
            $this->cancel();
        }

        $this->dispatch('toast', message: __('Section removed'));
    }

    public function render()
    {
        return view('livewire.admin.content.home-sections', [
            'sections' => $this->previewed(),
            'missingTypes' => $this->missingTypes(),
        ])->title(__('Home sections'));
    }

    /** Every section as it would look once its draft is published, in that order. */
    private function previewed(): Collection
    {
        return HomeSection::with('draft')->get()
            ->map(fn (HomeSection $section) => app(ContentDrafts::class)->preview($section))
            ->sortBy(fn (HomeSection $section) => [(int) $section->position, $section->id])
            ->values();
    }

    /** @return array<int, string> */
    private function missingTypes(): array
    {
        $existing = HomeSection::pluck('type')->all();

        return array_values(array_diff(self::TYPES, $existing));
    }
}
