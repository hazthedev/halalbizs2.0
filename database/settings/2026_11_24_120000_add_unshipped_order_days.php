<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // PAY-14 / HB-G143 (HB-DEC-1124d (2)): not shipped 7 days after the
        // order was placed (online: paid) → cancelled; seller warned on day 5.
        $this->migrator->add('order.unshipped_cancel_days', 7);
        $this->migrator->add('order.unshipped_warn_days', 5);
    }
};
