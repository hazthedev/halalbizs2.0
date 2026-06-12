<?php

namespace App\Settings;

use Illuminate\Support\Carbon;
use Spatie\LaravelSettings\Settings;

/**
 * Seasonal/occasion theming (announcement strip + home hero).
 * Hard rule 8: these colors style ONLY the announcement bar and hero
 * overlay — buttons, links, and prices keep the emerald/ink tokens.
 */
class ThemeSettings extends Settings
{
    public string $occasion;

    public bool $announcement_enabled;

    public string $announcement_text_en;

    public string $announcement_text_ms;

    public string $announcement_bg;

    public string $announcement_text_color;

    public bool $hero_image_enabled;

    /** ISO datetime string — null means "always". */
    public ?string $starts_at;

    /** ISO datetime string — null means "always". */
    public ?string $ends_at;

    public static function group(): string
    {
        return 'theme';
    }

    public static function defaults(): array
    {
        return [
            'occasion' => '',
            'announcement_enabled' => false,
            'announcement_text_en' => '',
            'announcement_text_ms' => '',
            'announcement_bg' => '#03392B',
            'announcement_text_color' => '#F7F7F4',
            'hero_image_enabled' => false,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function withinWindow(): bool
    {
        $now = now();

        try {
            if ($this->starts_at !== null && $now->lt(Carbon::parse($this->starts_at))) {
                return false;
            }

            if ($this->ends_at !== null && $now->gt(Carbon::parse($this->ends_at))) {
                return false;
            }
        } catch (\Throwable) {
            return true; // an unparseable schedule never hides content
        }

        return true;
    }

    public function announcementActive(): bool
    {
        return $this->announcement_enabled
            && $this->withinWindow()
            && trim($this->announcementText(app()->getLocale())) !== '';
    }

    public function heroActive(): bool
    {
        return $this->hero_image_enabled && $this->withinWindow();
    }

    /** Occasion text per locale — en is the fallback. */
    public function announcementText(string $locale): string
    {
        if ($locale === 'ms' && trim($this->announcement_text_ms) !== '') {
            return $this->announcement_text_ms;
        }

        return $this->announcement_text_en;
    }
}
