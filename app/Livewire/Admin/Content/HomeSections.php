<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\HomeSection;
use App\Models\ScheduledLayout;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\DisplayTime;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
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

    public string $showOn = 'both';

    /** The layout on screen: null = the everyday one. Client input, so every use goes through layout(). */
    #[Url(as: 'layout')]
    public ?int $layoutId = null;

    public bool $showLayoutForm = false;

    /** true = the form changes the chosen layout; false = it makes a new one. */
    public bool $editingLayout = false;

    public string $layoutName = '';

    /** Typed in Malaysia time (datetime-local), stored in UTC. */
    public string $layoutStarts = '';

    public string $layoutEnds = '';

    public function chooseLayout(?int $layoutId): void
    {
        $this->layoutId = $layoutId;
        $this->layoutId = $this->layout()?->id;
        $this->cancel();
        $this->cancelLayout();
    }

    public function newLayout(): void
    {
        $this->cancelLayout();
        $this->showLayoutForm = true;
    }

    public function editLayout(): void
    {
        if (($layout = $this->layout()) === null) {
            return;
        }

        $this->cancelLayout();
        $this->editingLayout = true;
        $this->showLayoutForm = true;
        $this->layoutName = $layout->name;
        $this->layoutStarts = $layout->starts_at->display()->format('Y-m-d\TH:i');
        $this->layoutEnds = $layout->ends_at->display()->format('Y-m-d\TH:i');
    }

    public function cancelLayout(): void
    {
        $this->reset(['showLayoutForm', 'editingLayout', 'layoutName', 'layoutStarts', 'layoutEnds']);
        $this->resetErrorBag();
    }

    /** A layout's name and dates go live at once (publisher only); its sections are drafted like any other. */
    public function saveLayout(): void
    {
        $this->authorize('cms.publish');

        $this->validate([
            'layoutName' => ['required', 'string', 'max:80'],
            'layoutStarts' => ['required', 'date'],
            'layoutEnds' => ['required', 'date', 'after:layoutStarts'],
        ], ['layoutEnds.after' => __('The layout must end after it starts.')], [
            'layoutName' => __('name'),
            'layoutStarts' => __('start'),
            'layoutEnds' => __('end'),
        ]);

        $values = [
            'name' => trim($this->layoutName),
            'starts_at' => DisplayTime::parse($this->layoutStarts),
            'ends_at' => DisplayTime::parse($this->layoutEnds),
        ];

        if ($this->editingLayout && ($layout = $this->layout()) !== null) {
            $layout->update($values);
        } else {
            $layout = ScheduledLayout::create($values);

            // Starts as a copy of what is live on the everyday layout, ready to change.
            HomeSection::whereNull('scheduled_layout_id')->where('is_active', true)->orderBy('position')->get()
                ->each(function (HomeSection $section) use ($layout) {
                    $copy = $section->replicate();
                    $copy->scheduled_layout_id = $layout->id;
                    $copy->save();
                });

            $this->layoutId = $layout->id;
        }

        $this->cancelLayout();
        $this->dispatch('toast', message: __('Saved. It is live now.'));
    }

    public function deleteLayout(int $layoutId): void
    {
        $this->authorize('cms.publish');

        ScheduledLayout::findOrFail($layoutId)->delete();

        if ($this->layoutId === $layoutId) {
            $this->chooseLayout(null);
        }

        $this->dispatch('toast', message: __('Layout deleted'));
    }

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
        $section->scheduled_layout_id = $this->layout()?->id;

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
        $this->showOn = $section->show_on ?? 'both';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'source', 'limit', 'showOn']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $section = app(ContentDrafts::class)->preview(HomeSection::findOrFail($this->editingId));

        $rules = [
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'title.vi' => ['nullable', 'string', 'max:255'],
            'showOn' => ['required', 'in:both,web,app'],
        ];

        if (in_array($section->type, ['category_grid', 'product_carousel', 'product_grid'], true)) {
            $rules['limit'] = ['required', 'integer', 'min:1', 'max:48'];
        }

        if (in_array($section->type, ['product_carousel', 'product_grid'], true)) {
            $rules['source'] = ['required', 'in:'.implode(',', self::SOURCES)];
        }

        $this->validate($rules, attributes: ['title.en' => __('title (English)')]);

        $section->show_on = $this->showOn;

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

        HomeSection::whereHas('draft')->where('scheduled_layout_id', $this->layout()?->id)->get()->each(fn (HomeSection $section) => app(ContentDrafts::class)->publish($section, auth()->user()));

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
            'layouts' => ScheduledLayout::orderBy('starts_at')->get(),
            'layout' => $this->layout(),
        ])->title(__('Home sections'));
    }

    /** Every section as it would look once its draft is published, in that order. */
    private function previewed(): Collection
    {
        return HomeSection::with('draft')->where('scheduled_layout_id', $this->layout()?->id)->get()
            ->map(fn (HomeSection $section) => app(ContentDrafts::class)->preview($section))
            ->sortBy(fn (HomeSection $section) => [(int) $section->position, $section->id])
            ->values();
    }

    private function layout(): ?ScheduledLayout
    {
        return $this->layoutId === null ? null : ScheduledLayout::find($this->layoutId);
    }

    /** @return array<int, string> */
    private function missingTypes(): array
    {
        $existing = HomeSection::where('scheduled_layout_id', $this->layout()?->id)->pluck('type')->all();

        return array_values(array_diff(self::TYPES, $existing));
    }
}
