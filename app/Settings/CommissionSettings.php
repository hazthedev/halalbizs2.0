<?php

namespace App\Settings;

use App\Enums\CommissionBasis;
use Spatie\LaravelSettings\Settings;

class CommissionSettings extends Settings
{
    public float $global_rate;

    /**
     * A CommissionBasis value ('gross' | 'net') — what the rate is charged ON.
     * Stored as a string rather than the enum so a future value can be added
     * without a settings migration; resolve it through basis() below.
     */
    public string $discount_basis;

    public function basis(): CommissionBasis
    {
        return CommissionBasis::tryFrom($this->discount_basis) ?? CommissionBasis::Net;
    }

    public static function group(): string
    {
        return 'commission';
    }
}
