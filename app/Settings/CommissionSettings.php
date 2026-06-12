<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CommissionSettings extends Settings
{
    public float $global_rate;

    public static function group(): string
    {
        return 'commission';
    }
}
