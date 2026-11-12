<?php

namespace App\Models;

use App\Models\Concerns\HasDrafts;
use App\Settings\ThemeSettings;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Singleton media anchor for seasonal theming (settings can't hold uploads).
 * One row per key — currently only 'hero'. The hero row also stands for
 * "the theme" in the draft flow: its draft carries the ThemeSettings values.
 */
class ThemeAsset extends Model implements HasMedia
{
    use HasDrafts;
    use InteractsWithMedia;

    protected $fillable = ['key'];

    /** The live settings as they were before a draft was put on the singleton (for the audit entry). */
    private ?array $settingsBefore = null;

    public static function hero(): self
    {
        return static::firstOrCreate(['key' => 'hero']);
    }

    /** The theme values are on ThemeSettings, not on this row. */
    public function draftState(): array
    {
        return app(ThemeSettings::class)->toArray();
    }

    /** In memory only, on the settings singleton; PreviewDrafts refreshes it after the response. */
    public function fillFromDraft(array $payload): void
    {
        $settings = app(ThemeSettings::class);
        $this->settingsBefore ??= $settings->toArray();

        foreach ($payload as $key => $value) {
            $settings->{$key} = $value;
        }
    }

    public function saveAsLive(): void
    {
        $settings = app(ThemeSettings::class);
        $before = $this->settingsBefore ?? $settings->toArray();
        $settings->save();

        // Settings are not a model, so the audit entry is written by hand: only what changed.
        $changed = array_filter($settings->toArray(), fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH);

        if ($changed !== []) {
            activity('theme')
                ->event('updated')
                ->withChanges(['old' => array_intersect_key($before, $changed), 'attributes' => $changed])
                ->log('Theme settings updated');
        }

        $this->settingsBefore = null;
    }

    public function isActiveNow(): bool
    {
        return true;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->width(1600)->performOnCollections('image');
    }
}
