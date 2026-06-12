<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TrackingSettings extends Settings
{
    public string $ga4_id;

    public string $meta_pixel_id;

    public string $tiktok_pixel_id;

    public static function group(): string
    {
        return 'tracking';
    }
}
