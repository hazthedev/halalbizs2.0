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

    /** PAY-14: not shipped this many days after placed (online: paid) → cancelled. */
    public int $unshipped_cancel_days;

    /** PAY-14: the seller's one "ship soon" warning, this many days in. */
    public int $unshipped_warn_days;

    public static function group(): string
    {
        return 'order';
    }
}
