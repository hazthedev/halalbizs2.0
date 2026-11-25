<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    public bool $require_product_approval;

    /** G247 (HB-DEC-1125b): OFF = an admin approves each WHIP-confirmed certificate. Superadmin only. */
    public bool $whip_auto_approve;

    public static function group(): string
    {
        return 'moderation';
    }
}
