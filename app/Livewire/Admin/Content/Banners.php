<?php

namespace App\Livewire\Admin\Content;

use App\Models\Banner;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Banner CRUD (docs/08 §G) — image, schedule window, position ordering.
 * Drives the storefront home `banner` section via Banner::active().
 */
#[Layout('layouts.admin')]
class Banners extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array{en: string, ms: string} */
    public array $title = ['en' => '', 'ms' => ''];

    public string $linkUrl = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = true;

    public ?TemporaryUploadedFile $image = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);

        $this->resetForm();
        $this->editingId = $banner->id;
        $this->title = [
            'en' => $banner->getTranslation('title', 'en'),
            'ms' => $banner->getTranslation('title', 'ms', false) ?? '',
        ];
        $this->linkUrl = $banner->link_url ?? '';
        $this->startsAt = $banner->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $banner->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->isActive = $banner->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'title.en' => ['required', 'string', 'max:255'],
            'title.ms' => ['nullable', 'string', 'max:255'],
            'linkUrl' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'image' => [$this->editingId === null ? 'required' : 'nullable', 'image', 'max:4096'],
        ], attributes: [
            'title.en' => __('title (English)'),
            'image' => __('banner image'),
        ]);

        $starts = $this->parseDate($this->startsAt);
        $ends = $this->parseDate($this->endsAt);

        if ($starts !== null && $ends !== null && $ends->lte($starts)) {
            throw ValidationException::withMessages([
                'endsAt' => __('The banner must end after it starts.'),
            ]);
        }

        $banner = $this->editingId !== null
            ? Banner::findOrFail($this->editingId)
            : new Banner(['position' => ((int) Banner::max('position')) + 1]);

        $banner->fill([
            'link_url' => trim($this->linkUrl) !== '' ? trim($this->linkUrl) : null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'is_active' => $this->isActive,
        ]);

        // en is ALWAYS written (fallback locale); ms only when filled.
        $banner->setTranslation('title', 'en', trim($this->title['en']));

        if (trim($this->title['ms'] ?? '') !== '') {
            $banner->setTranslation('title', 'ms', trim($this->title['ms']));
        } else {
            $banner->forgetTranslation('title', 'ms');
        }

        $banner->save();

        if ($this->image !== null) {
            // singleFile collection — replaces the previous image.
            $banner->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        $this->dispatch('toast', message: $this->editingId !== null ? __('Banner updated') : __('Banner created'));
        $this->resetForm();
    }

    public function toggleActive(int $bannerId): void
    {
        $banner = Banner::findOrFail($bannerId);
        $banner->update(['is_active' => ! $banner->is_active]);

        $this->dispatch('toast', message: $banner->is_active ? __('Banner enabled') : __('Banner disabled'));
    }

    /** Swap with the neighbour, then re-index positions 0..n. */
    public function move(int $bannerId, int $direction): void
    {
        $banners = Banner::orderBy('position')->orderBy('id')->get()->values();
        $index = $banners->search(fn (Banner $banner) => $banner->id === $bannerId);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || $target < 0 || ! isset($banners[$target])) {
            return;
        }

        $ordered = $banners->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach (array_values($ordered) as $position => $banner) {
            if ($banner->position !== $position) {
                $banner->update(['position' => $position]);
            }
        }
    }

    public function delete(int $bannerId): void
    {
        Banner::findOrFail($bannerId)->delete();

        if ($this->editingId === $bannerId) {
            $this->resetForm();
        }

        $this->dispatch('toast', message: __('Banner deleted'));
    }

    public function render()
    {
        return view('livewire.admin.content.banners', [
            'banners' => Banner::with('media')->orderBy('position')->orderBy('id')->get(),
        ])->title(__('Banners'));
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'title', 'linkUrl', 'startsAt', 'endsAt', 'isActive', 'image']);
        $this->resetErrorBag();
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
