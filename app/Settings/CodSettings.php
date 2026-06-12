<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CodSettings extends Settings
{
    public bool $enabled;

    public int $max_order_sen;

    public static function group(): string
    {
        return 'cod';
    }
}
