<?php

namespace App\Livewire\Admin\Content;

use App\Models\Banner;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use Closure;
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

    /** @var array{en: string, ms: string, vi: string} */
    public array $title = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $subtitle = ['en' => '', 'ms' => '', 'vi' => ''];

    /** @var array{en: string, ms: string, vi: string} */
    public array $ctaLabel = ['en' => '', 'ms' => '', 'vi' => ''];

    public string $linkUrl = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = true;

    public ?TemporaryUploadedFile $image = null;

    /** Optional Vietnamese artwork when the headline is baked into the image. */
    public ?TemporaryUploadedFile $imageVi = null;

    /** Optional motion slide (mp4/webm, ≤30MB) — the image stays as fallback. */
    public ?TemporaryUploadedFile $video = null;

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
        $this->title = ContentLocales::read($banner, 'title');
        $this->subtitle = ContentLocales::read($banner, 'subtitle');
        $this->ctaLabel = ContentLocales::read($banner, 'cta_label');
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
            'title.vi' => ['nullable', 'string', 'max:255'],
            'subtitle.en' => ['nullable', 'string', 'max:255'],
            'subtitle.ms' => ['nullable', 'string', 'max:255'],
            'subtitle.vi' => ['nullable', 'string', 'max:255'],
            'ctaLabel.en' => ['nullable', 'string', 'max:60'],
            'ctaLabel.ms' => ['nullable', 'string', 'max:60'],
            'ctaLabel.vi' => ['nullable', 'string', 'max:60'],
            // M-17: this lands in a live <a href> on the storefront hero, so a
            // cms.manage admin could otherwise plant `javascript:`. Blade escapes
            // the quotes but not the scheme. Relative paths must stay legal —
            // home.blade.php branches on them for wire:navigate.
            'linkUrl' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && trim($value) !== '' && ! HtmlSanitizer::isSafeUrl(trim($value))) {
                    $fail(__('Use a full https:// address or a path starting with /.'));
                }
            }],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'image' => [$this->editingId === null ? 'required' : 'nullable', 'image', 'max:4096'],
            'imageVi' => ['nullable', 'image', 'max:4096'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:30720'],
        ], [
            'video.mimetypes' => __('The video must be an MP4 or WebM file.'),
            'video.max' => __('The video must be 30MB or smaller.'),
        ], [
            'title.en' => __('title (English)'),
            'image' => __('banner image'),
            'video' => __('banner video'),
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

        ContentLocales::write($banner, 'title', $this->title);
        ContentLocales::write($banner, 'subtitle', $this->subtitle, englishRequired: false);
        ContentLocales::write($banner, 'cta_label', $this->ctaLabel, englishRequired: false);

        $banner->save();

        if ($this->image !== null) {
            // singleFile collection — replaces the previous image.
            $banner->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        if ($this->imageVi !== null) {
            $banner->addMedia($this->imageVi->getRealPath())
                ->usingFileName($this->imageVi->getClientOriginalName())
                ->toMediaCollection('image_vi');
        }

        if ($this->video !== null) {
            // singleFile collection — replaces the previous video.
            $banner->addMedia($this->video->getRealPath())
                ->usingFileName($this->video->getClientOriginalName())
                ->toMediaCollection('video');
        }

        $this->dispatch('toast', message: $this->editingId !== null ? __('Banner updated') : __('Banner created'));
        $this->resetForm();
    }

    /** Drops the motion slide — the banner falls back to its image. */
    public function removeVideo(): void
    {
        if ($this->editingId === null) {
            return;
        }

        Banner::findOrFail($this->editingId)->clearMediaCollection('video');

        $this->dispatch('toast', message: __('Banner video removed'));
    }

    public function removeVietnameseImage(): void
    {
        if ($this->editingId === null) {
            $this->imageVi = null;

            return;
        }

        Banner::findOrFail($this->editingId)->clearMediaCollection('image_vi');
        $this->imageVi = null;
        $this->dispatch('toast', message: __('Vietnamese banner image removed'));
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
        $this->reset(['showForm', 'editingId', 'title', 'subtitle', 'ctaLabel', 'linkUrl', 'startsAt', 'endsAt', 'isActive', 'image', 'imageVi', 'video']);
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
