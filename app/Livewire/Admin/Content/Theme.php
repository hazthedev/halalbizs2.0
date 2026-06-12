<?php

namespace App\Livewire\Admin\Content;

use App\Models\ThemeAsset;
use App\Settings\ThemeSettings;
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
    use WithFileUploads;

    public string $occasion = '';

    public bool $announcementEnabled = false;

    public string $announcementTextEn = '';

    public string $announcementTextMs = '';

    public string $announcementBg = '#03392B';

    public string $announcementTextColor = '#F7F7F4';

    public bool $heroImageEnabled = false;

    public string $startsAt = '';

    public string $endsAt = '';

    public ?TemporaryUploadedFile $heroImage = null;

    public function mount(ThemeSettings $settings): void
    {
        $this->loadFromSettings($settings);
    }

    public function save(): void
    {
        $this->validate([
            'occasion' => ['nullable', 'string', 'max:80'],
            'announcementTextEn' => [$this->announcementEnabled ? 'required' : 'nullable', 'string', 'max:200'],
            'announcementTextMs' => ['nullable', 'string', 'max:200'],
            'announcementBg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'announcementTextColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'heroImage' => ['nullable', 'image', 'max:4096'],
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

        $settings = app(ThemeSettings::class);
        $settings->occasion = trim($this->occasion);
        $settings->announcement_enabled = $this->announcementEnabled;
        $settings->announcement_text_en = trim($this->announcementTextEn);
        $settings->announcement_text_ms = trim($this->announcementTextMs);
        $settings->announcement_bg = strtoupper($this->announcementBg);
        $settings->announcement_text_color = strtoupper($this->announcementTextColor);
        $settings->hero_image_enabled = $this->heroImageEnabled;
        $settings->starts_at = $starts?->toIso8601String();
        $settings->ends_at = $ends?->toIso8601String();
        $settings->save();

        if ($this->heroImage !== null) {
            // singleFile collection — replaces the previous hero.
            ThemeAsset::hero()
                ->addMedia($this->heroImage->getRealPath())
                ->usingFileName($this->heroImage->getClientOriginalName())
                ->toMediaCollection('image');

            $this->heroImage = null;
        }

        $this->loadFromSettings($settings);
        $this->dispatch('toast', message: __('Theme saved'));
    }

    public function removeHeroImage(): void
    {
        ThemeAsset::hero()->clearMediaCollection('image');

        $this->dispatch('toast', message: __('Hero image removed'));
    }

    public function resetDefaults(): void
    {
        $settings = app(ThemeSettings::class);

        foreach (ThemeSettings::defaults() as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        ThemeAsset::hero()->clearMediaCollection('image');

        $this->heroImage = null;
        $this->loadFromSettings($settings);
        $this->resetErrorBag();
        $this->dispatch('toast', message: __('Theme reset to defaults'));
    }

    public function render()
    {
        $heroAsset = ThemeAsset::where('key', 'hero')->first();

        return view('livewire.admin.content.theme', [
            'heroUrl' => $heroAsset?->getFirstMediaUrl('image', 'card') ?: null,
        ])->title(__('Theme'));
    }

    private function loadFromSettings(ThemeSettings $settings): void
    {
        $this->occasion = $settings->occasion;
        $this->announcementEnabled = $settings->announcement_enabled;
        $this->announcementTextEn = $settings->announcement_text_en;
        $this->announcementTextMs = $settings->announcement_text_ms;
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
            return Carbon::parse($iso)->format('Y-m-d\TH:i');
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
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
