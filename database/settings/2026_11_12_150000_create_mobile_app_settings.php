<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mobile_app.min_version', '1.0.0');
        $this->migrator->add('mobile_app.maintenance_enabled', false);
        $this->migrator->add('mobile_app.maintenance_message_en', '');
        $this->migrator->add('mobile_app.maintenance_message_ms', '');
    }
};
