<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('boost.price_sen_per_day', 200); // RM2/day
        $this->migrator->add('boost.max_active_per_store', 3);
        $this->migrator->add('boost.enabled', true);
    }
};
