<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CodSettings extends Settings
{
    public bool $enabled;

    public int $max_order_sen;

    /** A store owing more than this (sen) in commission cannot take COD. */
    public int $debt_limit_sen;

    public static function group(): string
    {
        return 'cod';
    }
}
