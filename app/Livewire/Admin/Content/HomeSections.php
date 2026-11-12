<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ScheduledLayout;
use App\Models\Store;
use App\Services\ContentDrafts;
use App\Support\ContentLocales;
use App\Support\DisplayTime;
use App\Support\JsonSearch;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Home sections manager (docs/08 §G) — drives storefront Home (B1).
 * Payload keys MUST stay 'source' / 'limit' — App\Services\HomeLayout reads them.
 */
#[Layout('layouts.admin')]
class HomeSections extends Component
{
    use ManagesDrafts;
    use WithFileUploads;

    /** One section per type; only missing types are addable. */
    public const TYPES = ['banner', 'category_grid', 'product_carousel', 'product_grid', 'recommended', 'recently_viewed'];

    /** Types a layout may hold more than once (item 14). */
    public const REPEATABLE = ['picture_tiles', 'picked_products', 'picked_stores', 'notice'];

    /** Guard rail: sections per layout. */
    public const MAX_SECTIONS = 10;

    /** Hand-picked products or stores per section. */
    public const MAX_PICKS = 12;

    public const SOURCES = ['latest', 'top'];

    public string $addType = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $source = 'latest';

    public string $limit = '12';

    public string $showOn = 'both';

    /** notice: the text per language. @var array{en: string, ms: string, vi: string} */
    public array $noticeText = ['en' => '', 'ms' => '', 'vi' => ''];

    /** picked_products / picked_stores: chosen ids in show order. @var array<int, int> */
    public array $picked = [];

    public string $pickSearch = '';

    /** picture_tiles, keyed by slot 1..4: a new upload, the label per language, the link (a path on this site). */
    public array $tileImages = [];

    public array $tileLabels = [];

    public array $tileLinks = [];

    /** Slots taken off in this edit; their picture goes at publish. @var array<int, int> */
    public array $tileRemoved = [];

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
            'picture_tiles' => __('Picture tiles'),
            'picked_products' => __('Hand-picked products'),
            'picked_stores' => __('Hand-picked stores'),
            'notice' => __('Short notice'),
            default => $type,
        };
    }

    public function addSection(): void
    {
        $missing = $this->missingTypes();

        if (! in_array($this->addType, $missing, true)) {
            return;
        }

        if ($this->previewed()->count() >= self::MAX_SECTIONS) {
            $this->dispatch('toast', message: __('A layout holds at most :max sections. Remove one first.', ['max' => self::MAX_SECTIONS]), type: 'error');

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

        // The new types show nothing until they are filled in: open the editor straight away.
        if (in_array($section->type, self::REPEATABLE, true)) {
            $this->edit($section->id);
        }
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

        $this->reset(['noticeText', 'picked', 'pickSearch', 'tileImages', 'tileLabels', 'tileLinks', 'tileRemoved']);
        $this->noticeText = array_merge($this->noticeText, $section->payload['text'] ?? []);
        $this->picked = array_map('intval', $section->payload['product_ids'] ?? $section->payload['store_ids'] ?? []);

        foreach (HomeSection::TILE_SLOTS as $slot) {
            $tile = collect($section->payload['tiles'] ?? [])->firstWhere('slot', $slot) ?? [];
            $this->tileLabels[$slot] = array_merge(['en' => '', 'ms' => '', 'vi' => ''], $tile['label'] ?? []);
            $this->tileLinks[$slot] = (string) ($tile['link_url'] ?? '');
        }
    }

    /** Add a product or a store to the hand-picked list (existence checked; what is not on sale drops out on the home). */
    public function pick(int $id): void
    {
        $section = HomeSection::find($this->editingId);
        $model = match ($section?->type) {
            'picked_products' => Product::class,
            'picked_stores' => Store::class,
            default => null,
        };

        if ($model !== null && ! in_array($id, $this->picked, true) && count($this->picked) < self::MAX_PICKS && $model::whereKey($id)->exists()) {
            $this->picked[] = $id;
        }
    }

    public function unpick(int $id): void
    {
        $this->picked = array_values(array_diff($this->picked, [$id]));
    }

    public function removeTile(int $slot): void
    {
        if (! in_array($slot, HomeSection::TILE_SLOTS, true)) {
            return;
        }

        $this->tileRemoved = array_values(array_unique([...$this->tileRemoved, $slot]));
        unset($this->tileImages[$slot]);
        $this->tileLabels[$slot] = ['en' => '', 'ms' => '', 'vi' => ''];
        $this->tileLinks[$slot] = '';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'source', 'limit', 'showOn', 'noticeText', 'picked', 'pickSearch', 'tileImages', 'tileLabels', 'tileLinks', 'tileRemoved']);
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

        if ($section->type === 'notice') {
            $rules += [
                'noticeText.en' => ['required', 'string', 'max:200'],
                'noticeText.ms' => ['nullable', 'string', 'max:200'],
                'noticeText.vi' => ['nullable', 'string', 'max:200'],
            ];
        }

        if ($section->type === 'picture_tiles') {
            $rules += [
                'tileImages.*' => ['nullable', 'image', 'max:4096', 'dimensions:min_width=400,min_height=300'],
                'tileLabels.*.*' => ['nullable', 'string', 'max:40'],
                // A path on this site only ("/c/dates"), never another site.
                'tileLinks.*' => ['nullable', 'string', 'max:255', 'regex:#^/(?!/)[^\\\\\\s]*$#'],
            ];
        }

        $this->validate($rules, [
            'tileLinks.*.regex' => __('Use a path on this site, for example /c/dates.'),
            'tileImages.*.dimensions' => __('The picture must be at least 400 x 300.'),
        ], [
            'title.en' => __('title (English)'),
            'noticeText.en' => __('text (English)'),
            'tileImages.*' => __('picture'),
            'tileLinks.*' => __('link'),
        ]);

        // Slots that will have a picture after this save: kept ones plus new uploads.
        $uploads = array_filter($this->tileImages);
        $tileSlots = array_values(array_filter(HomeSection::TILE_SLOTS, fn (int $slot) => isset($uploads[$slot])
            || (! in_array($slot, $this->tileRemoved, true) && $section->getFirstMedia('tile_'.$slot) !== null)));

        if ($section->type === 'picture_tiles' && count($tileSlots) < 2) {
            throw ValidationException::withMessages(['tileImages' => __('Add at least two pictures.')]);
        }

        $section->show_on = $this->showOn;

        ContentLocales::write($section, 'title', $this->title, englishRequired: false);

        // Payload contract per type — keys identical to HomeLayout::sectionData().
        $section->payload = match ($section->type) {
            'category_grid' => ['limit' => (int) $this->limit],
            'product_carousel', 'product_grid' => ['source' => $this->source, 'limit' => (int) $this->limit],
            'notice' => ['text' => array_filter(array_map('trim', $this->noticeText), fn ($text) => $text !== '')],
            'picked_products' => ['product_ids' => array_slice(array_values(array_unique(array_map('intval', $this->picked))), 0, self::MAX_PICKS)],
            'picked_stores' => ['store_ids' => array_slice(array_values(array_unique(array_map('intval', $this->picked))), 0, self::MAX_PICKS)],
            'picture_tiles' => ['tiles' => array_map(fn (int $slot) => [
                'slot' => $slot,
                'label' => array_filter(array_map('trim', $this->tileLabels[$slot] ?? []), fn ($text) => $text !== ''),
                'link_url' => trim((string) ($this->tileLinks[$slot] ?? '')) ?: null,
            ], $tileSlots)],
            default => null,
        };

        $removed = $section->type === 'picture_tiles'
            ? array_map(fn (int $slot) => 'tile_'.$slot, array_diff($this->tileRemoved, array_keys($uploads)))
            : [];

        $draft = app(ContentDrafts::class)->stage($section, auth()->user(), withFiles: $uploads !== [], clearMedia: $removed);

        foreach ($removed as $collection) {
            $draft?->clearMediaCollection($collection);
        }

        foreach ($section->type === 'picture_tiles' ? $uploads : [] as $slot => $upload) {
            $draft->addMedia($upload->getRealPath())
                ->usingFileName($upload->getClientOriginalName())
                ->toMediaCollection('tile_'.$slot);
        }

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
            ...$this->pickerData(),
            'layout' => $this->layout(),
        ])->title(__('Home sections'));
    }

    /** Every section as it would look once its draft is published, in that order. */
    private function previewed(): Collection
    {
        return HomeSection::with(['draft.media', 'media'])->where('scheduled_layout_id', $this->layout()?->id)->get()
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

        return [...array_values(array_diff(self::TYPES, $existing)), ...self::REPEATABLE];
    }

    /**
     * For the hand-picked editor: the chosen items in order, and search results to add.
     *
     * @return array{pickedItems: Collection, pickResults: Collection}
     */
    private function pickerData(): array
    {
        $type = $this->editingId === null ? null : HomeSection::whereKey($this->editingId)->value('type');
        $query = match ($type) {
            'picked_products' => Product::query(),
            'picked_stores' => Store::query(),
            default => null,
        };

        if ($query === null) {
            return ['pickedItems' => collect(), 'pickResults' => collect()];
        }

        $found = (clone $query)->whereIn('id', $this->picked)->get()->keyBy('id');
        $term = trim($this->pickSearch);

        return [
            'pickedItems' => collect($this->picked)->map(fn ($id) => $found->get($id))->filter()->values(),
            'pickResults' => mb_strlen($term) < 2 ? collect() : ($type === 'picked_products' ? Product::live() : Store::approved())
                ->whereRaw(JsonSearch::clause('name'), [JsonSearch::pattern($term)])
                ->whereNotIn('id', $this->picked)
                ->orderBy('id')->take(8)->get(),
        ];
    }
}
