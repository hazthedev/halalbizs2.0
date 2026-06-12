<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class Ipay88Settings extends Settings
{
    public string $merchant_code;

    public string $merchant_key;

    public bool $sandbox;

    public static function group(): string
    {
        return 'ipay88';
    }

    public static function encrypted(): array
    {
        return ['merchant_key'];
    }
}
