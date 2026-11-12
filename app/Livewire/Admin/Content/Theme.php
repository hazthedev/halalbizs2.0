<?php

namespace App\Livewire\Admin\Content;

use App\Livewire\Concerns\ManagesDrafts;
use App\Models\ThemeAsset;
use App\Services\ContentDrafts;
use App\Settings\ThemeSettings;
use App\Support\DisplayTime;
use App\Support\ImageShape;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Occasion theming — announcement strip + home hero, schedule window.
 * Hard rule 8: these colors touch ONLY the announcement bar and hero;
 * buttons/links/prices keep the emerald and ink tokens everywhere.
 */
#[Layout('layouts.admin')]
class Theme extends Component
{
    use ManagesDrafts;
    use WithFileUploads;

    public string $occasion = '';

    public bool $announcementEnabled = false;

    public string $announcementTextEn = '';

    public string $announcementTextMs = '';

    public string $announcementTextVi = '';

    public string $announcementBg = '#03392B';

    public string $announcementTextColor = '#F7F7F4';

    public bool $heroImageEnabled = false;

    public string $startsAt = '';

    public string $endsAt = '';

    public ?TemporaryUploadedFile $heroImage = null;

    public function mount(): void
    {
        $this->loadFromSettings($this->previewedSettings());
    }

    public function save(): void
    {
        $this->validate([
            'occasion' => ['nullable', 'string', 'max:80'],
            'announcementTextEn' => [$this->announcementEnabled ? 'required' : 'nullable', 'string', 'max:200'],
            'announcementTextMs' => ['nullable', 'string', 'max:200'],
            'announcementTextVi' => ['nullable', 'string', 'max:200'],
            'announcementBg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'announcementTextColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'heroImage' => ['nullable', 'image', 'max:4096', ImageShape::hero()],
        ], messages: [
            'announcementBg.regex' => __('Use a 6-digit hex color like #03392B.'),
            'announcementTextColor.regex' => __('Use a 6-digit hex color like #F7F7F4.'),
        ], attributes: [
            'announcementTextEn' => __('announcement text (English)'),
        ]);

        $starts = $this->parseDate($this->startsAt);
        $ends = $this->parseDate($this->endsAt);

        if ($starts !== null && $ends !== null && $ends->lte($starts)) {
            throw ValidationException::withMessages([
                'endsAt' => __('The occasion must end after it starts.'),
            ]);
        }

        $draft = app(ContentDrafts::class)->stage(ThemeAsset::hero(), auth()->user(), [
            'occasion' => trim($this->occasion),
            'announcement_enabled' => $this->announcementEnabled,
            'announcement_text_en' => trim($this->announcementTextEn),
            'announcement_text_ms' => trim($this->announcementTextMs),
            'announcement_text_vi' => trim($this->announcementTextVi),
            'announcement_bg' => strtoupper($this->announcementBg),
            'announcement_text_color' => strtoupper($this->announcementTextColor),
            'hero_image_enabled' => $this->heroImageEnabled,
            'starts_at' => $starts?->toIso8601String(),
            'ends_at' => $ends?->toIso8601String(),
        ], withFiles: $this->heroImage !== null);

        if ($this->heroImage !== null) {
            // singleFile collection — replaces the previous hero draft; the live one stays until publish.
            $draft->addMedia($this->heroImage->getRealPath())
                ->usingFileName($this->heroImage->getClientOriginalName())
                ->toMediaCollection('image');

            $this->heroImage = null;
        }

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function removeHeroImage(): void
    {
        app(ContentDrafts::class)->stage(ThemeAsset::hero(), auth()->user(), clearMedia: ['image']);

        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function resetDefaults(): void
    {
        app(ContentDrafts::class)->stage(ThemeAsset::hero(), auth()->user(), ThemeSettings::defaults(), clearMedia: ['image']);

        $this->heroImage = null;
        $this->loadFromSettings($this->previewedSettings());
        $this->resetErrorBag();
        $this->dispatch('toast', message: __('Saved as a draft. It goes live when published.'));
    }

    public function render()
    {
        $heroAsset = ThemeAsset::where('key', 'hero')->with('draft')->first();
        $draft = $heroAsset?->draft;

        if ($heroAsset !== null) {
            $heroAsset = app(ContentDrafts::class)->preview($heroAsset);
            app(ThemeSettings::class)->refresh();
        }

        return view('livewire.admin.content.theme', [
            'heroUrl' => $heroAsset?->getFirstMediaUrl('image', 'card') ?: null,
            'draft' => $draft,
            'heroId' => $heroAsset?->id,
        ])->title(__('Theme'));
    }

    /** The form carries on from an open draft; the settings singleton goes back to the live values at once. */
    private function previewedSettings(): ThemeSettings
    {
        app(ContentDrafts::class)->preview(ThemeAsset::hero());
        $values = clone app(ThemeSettings::class);
        app(ThemeSettings::class)->refresh();

        return $values;
    }

    private function loadFromSettings(ThemeSettings $settings): void
    {
        $this->occasion = $settings->occasion;
        $this->announcementEnabled = $settings->announcement_enabled;
        $this->announcementTextEn = $settings->announcement_text_en;
        $this->announcementTextMs = $settings->announcement_text_ms;
        $this->announcementTextVi = $settings->announcement_text_vi;
        $this->announcementBg = $settings->announcement_bg;
        $this->announcementTextColor = $settings->announcement_text_color;
        $this->heroImageEnabled = $settings->hero_image_enabled;
        $this->startsAt = $this->toLocalInput($settings->starts_at);
        $this->endsAt = $this->toLocalInput($settings->ends_at);
    }

    private function toLocalInput(?string $iso): string
    {
        if ($iso === null || trim($iso) === '') {
            return '';
        }

        try {
            return Carbon::parse($iso)->display()->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return DisplayTime::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
