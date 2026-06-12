<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $default_locale;

    public array $enabled_locales;

    public string $base_currency;

    public array $display_currencies;

    public static function group(): string
    {
        return 'general';
    }
}
