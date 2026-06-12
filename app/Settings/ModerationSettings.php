<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    public bool $require_product_approval;

    public static function group(): string
    {
        return 'moderation';
    }
}
