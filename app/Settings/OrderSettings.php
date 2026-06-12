<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OrderSettings extends Settings
{
    public int $return_window_days;

    public int $auto_complete_days;

    public int $unpaid_expiry_minutes;

    public int $payout_min_sen;

    public int $return_seller_response_hours;

    public static function group(): string
    {
        return 'order';
    }
}
