<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BoostSettings extends Settings
{
    /** Flat price per boosted day, integer sen (RM2/day default). */
    public int $price_sen_per_day;

    /** How many boosts a store may run at once. */
    public int $max_active_per_store;

    public bool $enabled;

    public static function group(): string
    {
        return 'boost';
    }
}
