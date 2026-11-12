<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * What the phone app is told at start (GET /api/v1/config): the oldest
 * version still allowed in, and a maintenance notice. Web is not affected.
 */
class MobileAppSettings extends Settings
{
    /** Semantic version, e.g. 1.4.0. An app below it asks the user to update. */
    public string $min_version;

    public bool $maintenance_enabled;

    public string $maintenance_message_en;

    public string $maintenance_message_ms;

    public static function group(): string
    {
        return 'mobile_app';
    }

    /** English is the fallback, as everywhere else. */
    public function maintenanceMessage(string $locale): string
    {
        return $locale === 'ms' && trim($this->maintenance_message_ms) !== ''
            ? $this->maintenance_message_ms
            : $this->maintenance_message_en;
    }
}
